import os
from fastapi import APIRouter, HTTPException
from sqlalchemy import create_engine, text
import pandas as pd
from app.models.schemas import StudentGrowthRequest, StudentGrowthResponse
from app.services.trend_analysis import analyze_student_growth
from app.services.report_generator import generate_student_report

router = APIRouter()
DATABASE_URL = os.getenv("DATABASE_URL", "postgresql://kiduri:kiduri_secret@postgres:5432/kiduri")
engine = create_engine(DATABASE_URL)

DOMAINS = ["health_life", "motor_sensory", "cognitive_behavior", "language_communication", "social_relations"]


@router.post("/student/{student_id}/growth", response_model=StudentGrowthResponse)
def get_student_growth(student_id: int, request: StudentGrowthRequest = None):
    """生徒の成長分析"""
    query = text("""
        SELECT sr.*, dr.record_date
        FROM student_records sr
        JOIN daily_records dr ON sr.daily_record_id = dr.id
        WHERE sr.student_id = :student_id
        ORDER BY dr.record_date ASC
    """)
    with engine.connect() as conn:
        result = conn.execute(query, {"student_id": student_id})
        records = [dict(row._mapping) for row in result]

    if not records:
        raise HTTPException(status_code=404, detail="No records found for student")

    growth = analyze_student_growth(records, DOMAINS)

    student_query = text("SELECT student_name FROM students WHERE id = :id")
    with engine.connect() as conn:
        student = conn.execute(student_query, {"id": student_id}).fetchone()

    student_data = {"student_name": student.student_name if student else "", "student_id": student_id}
    report = generate_student_report(student_data, growth)

    return StudentGrowthResponse(
        student_id=student_id,
        domains=growth["domains"],
        trend=growth["trend"],
        overall_score=growth["overall_score"],
        period_comparison=report.get("period_comparison"),
    )
