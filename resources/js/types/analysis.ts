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
 * Suggestion interface
 */
export interface Suggestion {
  id: number;
  title: string;
  description: string;
  priority: SuggestionPriority;
  is_done: boolean;
}

/**
 * Alert interface
 */
export interface Alert {
  id: number;
  title: string;
  description: string;
  severity: 'critical' | 'warning' | 'info';
}

/**
 * Opportunity interface
 */
export interface Opportunity {
  id: number;
  title: string;
  description: string;
  potential_impact: string;
  estimated_revenue?: number;
}

/**
 * Analysis interface
 */
export interface Analysis {
  id: number;
  user_id: number;
  store_id: number;
  status: AnalysisStatus;
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
  store_id?: number;
  focus_areas?: string[];
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
