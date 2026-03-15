import os
from fastapi import APIRouter, HTTPException
from sqlalchemy import create_engine, text
import pandas as pd
from app.models.schemas import FacilityEvaluationRequest, FacilityEvaluationResponse
from app.services.statistics import calculate_satisfaction_rate

router = APIRouter()
DATABASE_URL = os.getenv("DATABASE_URL", "postgresql://kiduri:kiduri_secret@postgres:5432/kiduri")
engine = create_engine(DATABASE_URL)


@router.post("/facility/evaluation", response_model=FacilityEvaluationResponse)
def get_facility_evaluation(request: FacilityEvaluationRequest):
    """施設評価集計"""
    query = text("""
        SELECT fe.*, u.full_name as guardian_name, s.student_name
        FROM facility_evaluations fe
        JOIN users u ON fe.guardian_id = u.id
        JOIN students s ON fe.student_id = s.id
        WHERE fe.classroom_id = :classroom_id
        AND fe.evaluation_year = :year
    """)
    with engine.connect() as conn:
        result = conn.execute(query, {
            "classroom_id": request.classroom_id,
            "year": request.year,
        })
        evaluations = [dict(row._mapping) for row in result]

    if not evaluations:
        return FacilityEvaluationResponse(
            classroom_id=request.classroom_id,
            year=request.year,
            total_responses=0,
            categories=[],
            satisfaction_rate=0.0,
        )

    df = pd.DataFrame(evaluations)
    total = len(df)

    # Parse JSONB responses and calculate per-question stats
    categories = []
    all_responses = []
    for _, row in df.iterrows():
        responses = row.get("responses", {})
        if isinstance(responses, dict):
            for q_key, answer in responses.items():
                all_responses.append({"question": q_key, "answer": answer})

    if all_responses:
        resp_df = pd.DataFrame(all_responses)
        for question in resp_df["question"].unique():
            q_data = resp_df[resp_df["question"] == question]["answer"]
            counts = q_data.value_counts().to_dict()
            categories.append({
                "question": question,
                "counts": counts,
                "total": len(q_data),
            })

    # Calculate overall satisfaction
    responses_df = pd.DataFrame([e.get("responses", {}) for e in evaluations])
    satisfaction = calculate_satisfaction_rate(responses_df)

    return FacilityEvaluationResponse(
        classroom_id=request.classroom_id,
        year=request.year,
        total_responses=total,
        categories=categories,
        satisfaction_rate=round(satisfaction, 1),
    )
