import os
from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware
from app.routers import student_analysis, facility_evaluation, attendance_stats, support_plan_analysis

app = FastAPI(
    title="Kiduri Analytics Engine",
    description="統計分析・レポート生成エンジン",
    version="1.0.0",
)

app.add_middleware(
    CORSMiddleware,
    allow_origins=["http://localhost:8000"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

app.include_router(student_analysis.router, prefix="/api/analytics", tags=["Student Analysis"])
app.include_router(facility_evaluation.router, prefix="/api/analytics", tags=["Facility Evaluation"])
app.include_router(attendance_stats.router, prefix="/api/analytics", tags=["Attendance Stats"])
app.include_router(support_plan_analysis.router, prefix="/api/analytics", tags=["Support Plan Analysis"])


@app.get("/health")
def health_check():
    return {"status": "ok", "service": "kiduri-analytics"}
