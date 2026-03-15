import os
from fastapi import APIRouter
from sqlalchemy import create_engine, text
import pandas as pd
import numpy as np
from app.models.schemas import SupportPlanEffectivenessRequest, SupportPlanEffectivenessResponse

router = APIRouter()
DATABASE_URL = os.getenv("DATABASE_URL", "postgresql://kiduri:kiduri_secret@postgres:5432/kiduri")
engine = create_engine(DATABASE_URL)

DOMAIN_NAMES = {
    "health_life": "健康・生活",
    "motor_sensory": "運動・感覚",
    "cognitive_behavior": "認知・行動",
    "language_communication": "言語・コミュニケーション",
    "social_relations": "人間関係・社会性",
}


@router.post("/support-plan/effectiveness", response_model=SupportPlanEffectivenessResponse)
def get_support_plan_effectiveness(request: SupportPlanEffectivenessRequest):
    """支援計画の効果分析"""
    params = {"classroom_id": request.classroom_id}
    date_filter = ""
    if request.start_date:
        date_filter += " AND isp.created_date >= :start_date"
        params["start_date"] = request.start_date
    if request.end_date:
        date_filter += " AND isp.created_date <= :end_date"
        params["end_date"] = request.end_date

    # Get plans with their details
    plans_query = text(f"""
        SELECT isp.id, isp.student_id, isp.status, isp.created_date, isp.is_official
        FROM individual_support_plans isp
        WHERE isp.classroom_id = :classroom_id {date_filter}
        ORDER BY isp.created_date DESC
    """)
    with engine.connect() as conn:
        plans = [dict(r._mapping) for r in conn.execute(plans_query, params)]

    if not plans:
        return SupportPlanEffectivenessResponse(
            classroom_id=request.classroom_id,
            total_plans=0,
            achievement_distribution={},
            domain_effectiveness=[],
            average_completion_rate=0.0,
        )

    plan_ids = [p["id"] for p in plans]

    # Get plan details with achievement status
    details_query = text("""
        SELECT spd.plan_id, spd.domain, spd.achievement_status
        FROM support_plan_details spd
        WHERE spd.plan_id = ANY(:plan_ids)
    """)
    with engine.connect() as conn:
        details = [dict(r._mapping) for r in conn.execute(details_query, {"plan_ids": plan_ids})]

    # Get monitoring records
    monitoring_query = text("""
        SELECT mr.plan_id, md.domain, md.achievement_level
        FROM monitoring_records mr
        JOIN monitoring_details md ON md.monitoring_id = mr.id
        WHERE mr.plan_id = ANY(:plan_ids)
    """)
    with engine.connect() as conn:
        monitoring = [dict(r._mapping) for r in conn.execute(monitoring_query, {"plan_ids": plan_ids})]

    # Achievement distribution
    plan_df = pd.DataFrame(plans)
    status_counts = plan_df["status"].value_counts().to_dict()

    # Domain effectiveness
    domain_effectiveness = []
    if details:
        details_df = pd.DataFrame(details)
        for domain_key, domain_name in DOMAIN_NAMES.items():
            domain_details = details_df[details_df["domain"] == domain_key]
            if domain_details.empty:
                continue
            achievement_counts = domain_details["achievement_status"].value_counts().to_dict()
            total = len(domain_details)
            achieved = achievement_counts.get("achieved", 0) + achievement_counts.get("達成", 0)
            rate = (achieved / total * 100) if total > 0 else 0
            domain_effectiveness.append({
                "domain": domain_name,
                "domain_key": domain_key,
                "total_goals": total,
                "achievement_rate": round(rate, 1),
                "distribution": achievement_counts,
            })

    # Average completion rate
    official_plans = sum(1 for p in plans if p.get("is_official"))
    completion_rate = (official_plans / len(plans) * 100) if plans else 0

    return SupportPlanEffectivenessResponse(
        classroom_id=request.classroom_id,
        total_plans=len(plans),
        achievement_distribution=status_counts,
        domain_effectiveness=domain_effectiveness,
        average_completion_rate=round(completion_rate, 1),
    )
