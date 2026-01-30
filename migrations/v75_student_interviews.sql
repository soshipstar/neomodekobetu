-- 生徒面談記録テーブル
CREATE TABLE IF NOT EXISTS student_interviews (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  classroom_id INT NOT NULL,
  interview_date DATE NOT NULL,
  interviewer_id INT NOT NULL,
  interview_content TEXT,
  child_wish TEXT,
  check_school TINYINT(1) DEFAULT 0,
  check_school_note TEXT,
  check_home TINYINT(1) DEFAULT 0,
  check_home_note TEXT,
  check_troubles TINYINT(1) DEFAULT 0,
  check_troubles_note TEXT,
  other_notes TEXT,
  created_by INT NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  INDEX idx_student_id (student_id),
  INDEX idx_classroom_id (classroom_id),
  INDEX idx_interview_date (interview_date)
);
