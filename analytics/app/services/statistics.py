import numpy as np
from scipy import stats
import pandas as pd
from typing import Optional


def calculate_trend(values: list[float]) -> str:
    """傾向分析: 値のリストから上昇/下降/横ばいを判定"""
    if len(values) < 2:
        return "insufficient_data"
    x = np.arange(len(values))
    slope, _, r_value, p_value, _ = stats.linregress(x, values)
    if p_value > 0.05:
        return "stable"
    return "improving" if slope > 0 else "declining"


def calculate_domain_scores(records: list[dict], domains: list[str]) -> dict:
    """5領域のスコア算出"""
    result = {}
    for domain in domains:
        domain_values = [r.get(domain, 0) for r in records if r.get(domain) is not None]
        if domain_values:
            result[domain] = {
                "mean": float(np.mean(domain_values)),
                "std": float(np.std(domain_values)),
                "trend": calculate_trend(domain_values),
                "latest": domain_values[-1] if domain_values else None,
                "count": len(domain_values),
            }
        else:
            result[domain] = {"mean": 0, "std": 0, "trend": "no_data", "latest": None, "count": 0}
    return result


def calculate_satisfaction_rate(responses: pd.DataFrame, positive_values: list[str] = None) -> float:
    """満足度算出"""
    if positive_values is None:
        positive_values = ["yes", "はい", "満足", "とても満足"]
    if responses.empty:
        return 0.0
    total = len(responses)
    positive = responses.isin(positive_values).sum().sum()
    total_answers = responses.count().sum()
    return float(positive / total_answers * 100) if total_answers > 0 else 0.0


def period_comparison(current: dict, previous: dict) -> dict:
    """期間比較分析"""
    comparison = {}
    for key in current:
        if key in previous:
            current_val = current[key].get("mean", 0) if isinstance(current[key], dict) else current[key]
            previous_val = previous[key].get("mean", 0) if isinstance(previous[key], dict) else previous[key]
            if previous_val != 0:
                change_pct = ((current_val - previous_val) / abs(previous_val)) * 100
            else:
                change_pct = 0
            comparison[key] = {
                "current": current_val,
                "previous": previous_val,
                "change_percent": round(change_pct, 1),
                "direction": "up" if change_pct > 0 else "down" if change_pct < 0 else "same",
            }
    return comparison
