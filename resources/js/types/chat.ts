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
  id: string;  // UUID
  conversation_id: string;  // UUID
  role: MessageRole;
  content: string;
  created_at: string;
}

/**
 * Chat conversation interface
 */
export interface ChatConversation {
  id: string;  // UUID
  user_id: string;  // UUID
  store_id: string | null;  // UUID
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
  conversation_id?: string;  // UUID
  content: string;
  store_id?: string;  // UUID
}

/**
 * Chat filter
 */
export interface ChatFilter {
  search?: string;
  store_id?: string;  // UUID
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
  userId?: string;  // UUID
}
