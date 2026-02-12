/**
 * Analysis Type Definitions
 *
 * Defines all AI analysis-related TypeScript interfaces.
 */

/**
 * Analysis status enum
 */
export type AnalysisStatus = 'Pending' | 'Processing' | 'Completed' | 'Failed';

/**
 * Suggestion priority enum
 */
export type SuggestionPriority = 'high' | 'medium' | 'low';

/**
 * Suggestion status enum
 */
export type SuggestionStatus = 'new' | 'accepted' | 'in_progress' | 'completed' | 'rejected' | 'ignored';

/**
 * Suggestion step status enum
 */
export type StepStatus = 'pending' | 'completed';

/**
 * Suggestion step interface
 */
export interface SuggestionStep {
  id: number;
  suggestion_id: number;
  title: string;
  description?: string;
  position: number;
  is_custom: boolean;
  status: StepStatus;
  completed_at?: string;
  completed_by?: number;
  completedBy?: import('./user').User;
  created_at?: string;
  updated_at?: string;
}

/**
 * Suggestion task status enum
 */
export type TaskStatus = 'pending' | 'in_progress' | 'completed';

/**
 * Suggestion task interface
 */
export interface SuggestionTask {
  id: number;
  suggestion_id: number;
  step_index?: number | null;  // null = tarefa geral
  title: string;
  description?: string | null;
  status: TaskStatus;
  due_date?: string | null;
  completed_at?: string | null;
  completed_by?: number | null;
  created_by?: number | null;
  created_at?: string;
  updated_at?: string;
}

/**
 * Suggestion comment interface
 */
export interface SuggestionComment {
  id: number;
  suggestion_id: number;
  step_id?: number;
  user_id: number;
  content: string;
  created_at: string;
  updated_at?: string;
  user?: import('./user').User;
}

/**
 * Suggestion interface
 */
export interface Suggestion {
  id: string;  // UUID
  title: string;
  description: string;
  priority: SuggestionPriority;
  is_done: boolean;
  status?: SuggestionStatus;
  steps?: SuggestionStep[];
  comments?: SuggestionComment[];
  steps_count?: number;
  completed_steps_count?: number;
  comments_count?: number;
}

/**
 * Alert interface
 */
export interface Alert {
  id: string;  // UUID
  title: string;
  description: string;
  severity: 'critical' | 'warning' | 'info';
}

/**
 * Opportunity interface
 */
export interface Opportunity {
  id: string;  // UUID
  title: string;
  description: string;
  potential_impact: string;
  estimated_revenue?: number;
}

/**
 * Analysis type option (from GET /analysis/types)
 */
export interface AnalysisTypeOption {
  key: string;
  label: string;
  description: string;
  available: boolean;
  is_default: boolean;
}

/**
 * Analysis interface
 */
export interface Analysis {
  id: string;  // UUID
  user_id: string;  // UUID
  store_id: string;  // UUID
  status: AnalysisStatus;
  analysis_type?: string;
  analysis_type_label?: string;
  health_score: number;
  suggestions: Suggestion[];
  alerts: Alert[];
  opportunities: Opportunity[];
  analysis_date: string;
  created_at: string;
  updated_at: string;
}

/**
 * Analysis request payload
 */
export interface AnalysisRequest {
  store_id?: string;  // UUID
  focus_areas?: string[];
  analysis_type?: string;
}

/**
 * Analysis filter
 */
export interface AnalysisFilter {
  status?: AnalysisStatus;
  min_health_score?: number;
  start_date?: string;
  end_date?: string;
}

/**
 * Analysis summary
 */
export interface AnalysisSummary {
  total_analyses: number;
  average_health_score: number;
  pending_suggestions: number;
  critical_alerts: number;
  total_opportunities: number;
}

/**
 * Impact Dashboard Types
 */

export interface ImpactMetrics {
  revenue: number;
  orders: number;
  avg_ticket: number;
  days: number;
  daily_revenue: number;
  daily_orders: number;
}

export interface ImpactSummary {
  has_data: boolean;
  suggestions_in_progress?: number;
  suggestions_completed?: number;
  before?: ImpactMetrics;
  after?: ImpactMetrics;
  period?: {
    before: { start: string; end: string };
    after: { start: string; end: string };
  };
}

export interface TrendAnalysis {
  has_data: boolean;
  pre_trend?: number;
  post_trend?: number;
  acceleration?: number;
  interpretation?: 'significant_improvement' | 'slight_improvement' | 'stable' | 'decline';
}

export interface CategoryImpact {
  category: string;
  count: number;
  in_progress: number;
  completed: number;
  successful: number;
}

export interface TimelineSuggestion {
  id: number;
  title: string;
  category: string;
  status: string;
  in_progress_at: string | null;
  completed_at: string | null;
}

export interface DailyMetric {
  date: string;
  revenue: number;
  orders: number;
}

export interface ImpactDashboardData {
  summary: ImpactSummary;
  by_category: CategoryImpact[];
  timeline: {
    suggestions: TimelineSuggestion[];
    daily_metrics: DailyMetric[];
  };
  trend_analysis: TrendAnalysis;
}
