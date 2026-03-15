import os
from fastapi import APIRouter
from sqlalchemy import create_engine, text
import pandas as pd
from datetime import date, timedelta
import calendar
from app.models.schemas import AttendanceStatsRequest, AttendanceStatsResponse

router = APIRouter()
DATABASE_URL = os.getenv("DATABASE_URL", "postgresql://kiduri:kiduri_secret@postgres:5432/kiduri")
engine = create_engine(DATABASE_URL)

DAY_COLUMNS = {
    0: "scheduled_monday", 1: "scheduled_tuesday", 2: "scheduled_wednesday",
    3: "scheduled_thursday", 4: "scheduled_friday", 5: "scheduled_saturday", 6: "scheduled_sunday",
}


@router.post("/attendance/stats", response_model=AttendanceStatsResponse)
def get_attendance_stats(request: AttendanceStatsRequest):
    """出欠統計"""
    year, month = request.year, request.month
    _, last_day = calendar.monthrange(year, month)
    start = date(year, month, 1)
    end = date(year, month, last_day)

    # Get students in classroom
    students_query = text("""
        SELECT id, student_name, scheduled_monday, scheduled_tuesday,
               scheduled_wednesday, scheduled_thursday, scheduled_friday,
               scheduled_saturday, scheduled_sunday
        FROM students
        WHERE classroom_id = :classroom_id AND status = 'active'
    """)
    with engine.connect() as conn:
        students = [dict(r._mapping) for r in conn.execute(students_query, {"classroom_id": request.classroom_id})]

    # Get absences
    absences_query = text("""
        SELECT student_id, absence_date, reason
        FROM absence_notifications
        WHERE student_id IN (SELECT id FROM students WHERE classroom_id = :classroom_id)
        AND absence_date BETWEEN :start AND :end
    """)
    with engine.connect() as conn:
        absences = [dict(r._mapping) for r in conn.execute(absences_query, {
            "classroom_id": request.classroom_id, "start": start, "end": end
        })]

    absence_df = pd.DataFrame(absences) if absences else pd.DataFrame(columns=["student_id", "absence_date", "reason"])
    total_students = len(students)

    # Daily breakdown
    daily_breakdown = []
    for day in range(1, last_day + 1):
        d = date(year, month, day)
        weekday = d.weekday()
        scheduled_col = DAY_COLUMNS.get(weekday)
        if scheduled_col:
            scheduled = sum(1 for s in students if s.get(scheduled_col, False))
        else:
            scheduled = 0
        absent = len(absence_df[absence_df["absence_date"] == d]) if not absence_df.empty else 0
        present = max(0, scheduled - absent)
        rate = (present / scheduled * 100) if scheduled > 0 else 0
        daily_breakdown.append({
            "date": d.isoformat(),
            "weekday": weekday,
            "scheduled": scheduled,
            "present": present,
            "absent": absent,
            "attendance_rate": round(rate, 1),
        })

    # Student breakdown
    student_breakdown = []
    for student in students:
        sid = student["id"]
        student_absences = len(absence_df[absence_df["student_id"] == sid]) if not absence_df.empty else 0
        scheduled_days = sum(
            1 for day in range(1, last_day + 1)
            if student.get(DAY_COLUMNS.get(date(year, month, day).weekday()), False)
        )
        present_days = max(0, scheduled_days - student_absences)
        rate = (present_days / scheduled_days * 100) if scheduled_days > 0 else 0
        student_breakdown.append({
            "student_id": sid,
            "student_name": student["student_name"],
            "scheduled_days": scheduled_days,
            "present_days": present_days,
            "absent_days": student_absences,
            "attendance_rate": round(rate, 1),
        })

    # Absence reasons
    absence_reasons = []
    if not absence_df.empty and "reason" in absence_df.columns:
        reasons = absence_df["reason"].dropna().value_counts().head(10)
        absence_reasons = [{"reason": r, "count": int(c)} for r, c in reasons.items()]

    avg_rate = (
        sum(d["attendance_rate"] for d in daily_breakdown if d["scheduled"] > 0) /
        max(1, sum(1 for d in daily_breakdown if d["scheduled"] > 0))
    )

    return AttendanceStatsResponse(
        classroom_id=request.classroom_id,
        year=year,
        month=month,
        total_students=total_students,
        average_attendance_rate=round(avg_rate, 1),
        daily_breakdown=daily_breakdown,
        student_breakdown=student_breakdown,
        absence_reasons=absence_reasons,
    )
