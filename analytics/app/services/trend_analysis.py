import pandas as pd
import numpy as np
from scipy import stats
from datetime import date, timedelta


def analyze_student_growth(records: list[dict], domains: list[str]) -> dict:
    """生徒の成長傾向を時系列分析"""
    if not records:
        return {"trend": "no_data", "domains": {}, "overall_score": 0}

    df = pd.DataFrame(records)
    if "record_date" in df.columns:
        df["record_date"] = pd.to_datetime(df["record_date"])
        df = df.sort_values("record_date")

    domain_results = {}
    scores = []
    for domain in domains:
        if domain not in df.columns:
            domain_results[domain] = {"trend": "no_data", "values": []}
            continue
        values = df[domain].dropna().tolist()
        if len(values) >= 2:
            x = np.arange(len(values))
            slope, intercept, r_value, p_value, std_err = stats.linregress(x, values)
            trend = "improving" if slope > 0.01 else "declining" if slope < -0.01 else "stable"
            domain_results[domain] = {
                "trend": trend,
                "slope": round(float(slope), 4),
                "r_squared": round(float(r_value ** 2), 4),
                "latest": values[-1] if values else None,
                "mean": round(float(np.mean(values)), 2),
                "values": values,
            }
            scores.append(float(np.mean(values)))
        else:
            domain_results[domain] = {
                "trend": "insufficient_data",
                "values": values,
                "latest": values[-1] if values else None,
            }
            if values:
                scores.append(float(np.mean(values)))

    overall_score = round(float(np.mean(scores)), 2) if scores else 0
    trends = [d["trend"] for d in domain_results.values() if d["trend"] not in ("no_data", "insufficient_data")]
    improving = sum(1 for t in trends if t == "improving")
    declining = sum(1 for t in trends if t == "declining")
    if improving > declining:
        overall_trend = "improving"
    elif declining > improving:
        overall_trend = "declining"
    else:
        overall_trend = "stable"

    return {
        "trend": overall_trend,
        "domains": domain_results,
        "overall_score": overall_score,
    }


def analyze_attendance_trend(attendance_data: list[dict]) -> dict:
    """出欠傾向分析"""
    if not attendance_data:
        return {"trend": "no_data", "average_rate": 0}

    df = pd.DataFrame(attendance_data)
    if "date" in df.columns and "attendance_rate" in df.columns:
        df["date"] = pd.to_datetime(df["date"])
        df = df.sort_values("date")
        rates = df["attendance_rate"].tolist()
        x = np.arange(len(rates))
        if len(rates) >= 2:
            slope, _, _, _, _ = stats.linregress(x, rates)
            trend = "improving" if slope > 0.5 else "declining" if slope < -0.5 else "stable"
        else:
            trend = "insufficient_data"
        return {
            "trend": trend,
            "average_rate": round(float(np.mean(rates)), 1),
            "latest_rate": rates[-1] if rates else 0,
        }
    return {"trend": "no_data", "average_rate": 0}
