import numpy as np
from sklearn.cluster import KMeans
from sklearn.preprocessing import StandardScaler
import pandas as pd
from typing import Optional


def cluster_students(student_data: pd.DataFrame, n_clusters: int = 3) -> dict:
    """生徒をクラスタリングして類似グループを特定"""
    feature_columns = [
        "health_life", "motor_sensory", "cognitive_behavior",
        "language_communication", "social_relations"
    ]
    available_cols = [c for c in feature_columns if c in student_data.columns]
    if not available_cols or len(student_data) < n_clusters:
        return {"clusters": [], "message": "insufficient_data"}

    features = student_data[available_cols].fillna(0)
    scaler = StandardScaler()
    scaled = scaler.fit_transform(features)

    kmeans = KMeans(n_clusters=min(n_clusters, len(features)), random_state=42, n_init=10)
    labels = kmeans.fit_predict(scaled)

    clusters = []
    for i in range(kmeans.n_clusters):
        mask = labels == i
        cluster_data = student_data[mask]
        cluster_features = features[mask]
        clusters.append({
            "cluster_id": i,
            "student_count": int(mask.sum()),
            "student_ids": cluster_data["student_id"].tolist() if "student_id" in cluster_data.columns else [],
            "centroid": {col: float(v) for col, v in zip(available_cols, kmeans.cluster_centers_[i])},
            "characteristics": _describe_cluster(cluster_features, available_cols),
        })

    return {"clusters": clusters, "n_clusters": kmeans.n_clusters}


def _describe_cluster(cluster_df: pd.DataFrame, columns: list[str]) -> list[str]:
    """クラスタの特徴を日本語で記述"""
    domain_names = {
        "health_life": "健康・生活",
        "motor_sensory": "運動・感覚",
        "cognitive_behavior": "認知・行動",
        "language_communication": "言語・コミュニケーション",
        "social_relations": "人間関係・社会性",
    }
    means = cluster_df.mean()
    descriptions = []
    strongest = means.idxmax()
    weakest = means.idxmin()
    if strongest in domain_names:
        descriptions.append(f"{domain_names[strongest]}が強み")
    if weakest in domain_names and weakest != strongest:
        descriptions.append(f"{domain_names[weakest]}に課題")
    return descriptions
