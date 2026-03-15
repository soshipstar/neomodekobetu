from pydantic import BaseModel
from typing import Optional
from datetime import date


class StudentGrowthRequest(BaseModel):
    student_id: int
    start_date: Optional[date] = None
    end_date: Optional[date] = None


class StudentGrowthResponse(BaseModel):
    student_id: int
    domains: dict
    trend: str
    overall_score: float
    period_comparison: Optional[dict] = None


class FacilityEvaluationRequest(BaseModel):
    classroom_id: int
    year: int


class FacilityEvaluationResponse(BaseModel):
    classroom_id: int
    year: int
    total_responses: int
    categories: list[dict]
    satisfaction_rate: float
    year_over_year: Optional[dict] = None


class AttendanceStatsRequest(BaseModel):
    classroom_id: int
    year: int
    month: int


class AttendanceStatsResponse(BaseModel):
    classroom_id: int
    year: int
    month: int
    total_students: int
    average_attendance_rate: float
    daily_breakdown: list[dict]
    student_breakdown: list[dict]
    absence_reasons: list[dict]


class SupportPlanEffectivenessRequest(BaseModel):
    classroom_id: int
    start_date: Optional[date] = None
    end_date: Optional[date] = None


class SupportPlanEffectivenessResponse(BaseModel):
    classroom_id: int
    total_plans: int
    achievement_distribution: dict
    domain_effectiveness: list[dict]
    average_completion_rate: float
