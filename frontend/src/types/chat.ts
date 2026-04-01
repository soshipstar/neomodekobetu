import type { User, Student } from './user';

export type SenderType = 'guardian' | 'staff' | 'admin';
export type MessageType = 'normal' | 'absence_notification' | 'event_registration' | 'meeting_request' | 'meeting_counter' | 'meeting_confirmed' | 'broadcast';

export interface ChatRoom {
  id: number;
  student_id: number;
  guardian_id: number;
  last_message_at: string | null;
  created_at: string;
  student?: Student;
  guardian?: User;
  last_message?: ChatMessage | string;
  unread_count?: number;
  is_pinned?: boolean;
}

export interface ChatMessage {
  id: number;
  room_id: number;
  sender_id: number;
  sender_type: SenderType;
  message: string;
  message_type: MessageType;
  attachment_path: string | null;
  attachment_name: string | null;
  attachment_size: number | null;
  attachment_mime: string | null;
  meeting_request_id: number | null;
  is_read: boolean;
  is_read_by_staff: boolean;
  is_read_by_recipient: boolean;
  is_deleted: boolean;
  is_archived: boolean;
  deleted_at: string | null;
  created_at: string;
  updated_at: string;
  sender?: { id: number; full_name: string } | null;
}

export interface ChatAttachment {
  path: string;
  name: string;
  size: number;
  mime: string;
  url: string;
}
