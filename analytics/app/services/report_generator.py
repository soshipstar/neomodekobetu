import pandas as pd
import numpy as np
from datetime import date


def generate_student_report(student_data: dict, growth_analysis: dict) -> dict:
    """生徒の総合レポート生成"""
    domain_names = {
        "health_life": "健康・生活",
        "motor_sensory": "運動・感覚",
        "cognitive_behavior": "認知・行動",
        "language_communication": "言語・コミュニケーション",
        "social_relations": "人間関係・社会性",
    }

    report = {
        "student_name": student_data.get("student_name", ""),
        "generated_at": date.today().isoformat(),
        "overall_trend": growth_analysis.get("trend", "unknown"),
        "overall_score": growth_analysis.get("overall_score", 0),
        "domain_summaries": [],
        "strengths": [],
        "areas_for_improvement": [],
        "recommendations": [],
    }

    domains = growth_analysis.get("domains", {})
    for key, name in domain_names.items():
        domain = domains.get(key, {})
        summary = {
            "domain": name,
            "domain_key": key,
            "trend": domain.get("trend", "no_data"),
            "latest_score": domain.get("latest"),
            "mean_score": domain.get("mean"),
        }
        report["domain_summaries"].append(summary)

        if domain.get("trend") == "improving":
            report["strengths"].append(f"{name}は改善傾向にあります")
        elif domain.get("trend") == "declining":
            report["areas_for_improvement"].append(f"{name}に注力が必要です")

    return report


def generate_facility_report(evaluation_data: list[dict], year: int) -> dict:
    """施設評価レポート生成"""
    if not evaluation_data:
        return {"year": year, "total_responses": 0, "categories": []}

    df = pd.DataFrame(evaluation_data)
    total = len(df)

    return {
        "year": year,
        "total_responses": total,
        "generated_at": date.today().isoformat(),
    }


def generate_attendance_report(
    classroom_id: int, year: int, month: int,
    daily_data: list[dict], student_data: list[dict]
) -> dict:
    """出欠レポート生成"""
    if not daily_data:
        return {
            "classroom_id": classroom_id,
            "year": year,
            "month": month,
            "total_students": 0,
            "average_attendance_rate": 0,
            "daily_breakdown": [],
            "student_breakdown": [],
            "absence_reasons": [],
        }

    daily_df = pd.DataFrame(daily_data)
    student_df = pd.DataFrame(student_data) if student_data else pd.DataFrame()

    avg_rate = float(daily_df["attendance_rate"].mean()) if "attendance_rate" in daily_df.columns else 0

    return {
        "classroom_id": classroom_id,
        "year": year,
        "month": month,
        "total_students": len(student_df) if not student_df.empty else 0,
        "average_attendance_rate": round(avg_rate, 1),
        "daily_breakdown": daily_data,
        "student_breakdown": student_data or [],
        "absence_reasons": [],
        "generated_at": date.today().isoformat(),
    }
