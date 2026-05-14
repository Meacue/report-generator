export interface SyncStatus {
  status: "never" | "success" | "failed" | "in_progress";
  current_step?: "gitlab" | "bitrix24" | "matching" | null;
  started_at?: string | null;
  last_sync_at: string | null;
  source: string | null;
  items_synced: number;
  error_message?: string | null;
}

export interface Settings {
  gitlab_username: string | null;
  gitlab_email: string | null;
  bitrix24_user_id: string | null;
  bitrix24_webhook_configured: boolean;
  llm_provider: string;
  llm_system_prompt: string | null;
  enriched_prompt_enabled: boolean;
  developer_name: string | null;
  developer_position: string | null;
  sync_schedule_time: string;
  has_gitlab_token: boolean;
  has_bitrix24_api_key: boolean;
  has_llm_api_key: boolean;
}

export interface SettingsInput {
  gitlab_token?: string;
  gitlab_username?: string;
  gitlab_email?: string;
  bitrix24_webhook_url?: string;
  llm_provider?: string;
  llm_api_key?: string;
  llm_system_prompt?: string;
  enriched_prompt_enabled?: boolean;
  developer_name?: string;
  developer_position?: string;
  sync_schedule_time?: string;
}

export interface Report {
  id: number;
  type: string;
  date_from: string;
  date_to: string;
  status: string;
  days: ReportDay[];
}

export interface ReportDay {
  date: string;
  narrative: string | null;
  source: string;
  is_edited: boolean;
  tasks: ReportTask[];
}

export interface ReportTask {
  id: number | null;
  title: string;
  project_name: string | null;
  narrative: string | null;
  is_edited: boolean;
}

export interface ApiResponse<T> {
  data: T;
}

export interface PaginatedResponse<T> {
  data: T[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

export interface InboxItem {
  id: number;
  branch_name: string;
  gitlab_repo_id: number;
  parsed_task_number: number | null;
  parsed_date: string | null;
  parsed_info: string | null;
  confidence_level: string | null;
  commits_count: number;
  last_commit: string | null;
  synced_at: string | null;
}
