/**
 * Chat Type Definitions
 *
 * Defines all AI chat-related TypeScript interfaces.
 */

/**
 * Chat message role
 */
export type MessageRole = 'user' | 'assistant' | 'system';

/**
 * Chat message interface
 */
export interface ChatMessage {
  id: number;
  conversation_id: number;
  role: MessageRole;
  content: string;
  created_at: string;
}

/**
 * Chat conversation interface
 */
export interface ChatConversation {
  id: number;
  user_id: number;
  store_id: number | null;
  title: string;
  last_message_at: string;
  created_at: string;
  updated_at: string;
  messages?: ChatMessage[];
}

/**
 * New message payload
 */
export interface NewMessagePayload {
  conversation_id?: number;
  content: string;
  store_id?: number;
}

/**
 * Chat filter
 */
export interface ChatFilter {
  search?: string;
  store_id?: number;
  start_date?: string;
  end_date?: string;
}

/**
 * Chat stats
 */
export interface ChatStats {
  total_conversations: number;
  total_messages: number;
  active_conversations: number;
}

/**
 * Typing indicator state
 */
export interface TypingState {
  isTyping: boolean;
  userId?: number;
}
