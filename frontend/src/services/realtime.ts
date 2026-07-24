import { ref } from 'vue';
import axios from 'axios';

class RealtimeClient {
  private eventSource: EventSource | null = null;
  private token: string = '';
  private lastEventId: number = 0;
  private reconnectTimeout: any = null;
  private backoffTime: number = 2000; // start at 2s
  private maxBackoff: number = 30000;  // max 30s
  private fallbackInterval: any = null;
  
  public status = ref<'OFFLINE' | 'ONLINE' | 'RECONNECTING' | 'FALLBACK'>('OFFLINE');
  
  // Callbacks list grouped by event type
  private listeners: Record<string, ((data: any) => void)[]> = {};

  constructor() {
    this.listeners = {};
  }

  /**
   * Connect to the SSE stream.
   */
  public connect(token: string) {
    if (!token) return;
    this.token = token;
    
    this.cleanup();
    
    const url = `http://${window.location.hostname}:8500/api/realtime/stream?token=${encodeURIComponent(token)}&last_event_id=${this.lastEventId}`;
    
    try {
      this.eventSource = new EventSource(url);
      this.status.value = 'RECONNECTING';

      this.eventSource.onopen = () => {
        this.status.value = 'ONLINE';
        this.backoffTime = 2000; // Reset backoff
        
        // Stop fallback polling if running
        if (this.fallbackInterval) {
          clearInterval(this.fallbackInterval);
          this.fallbackInterval = null;
        }

        // Trigger a sync snapshot event to fetch fresh state after reconnecting
        this.trigger('reconnected', { lastEventId: this.lastEventId });
      };

      this.eventSource.onmessage = (event) => {
        try {
          const data = JSON.parse(event.data);
          if (event.lastEventId) {
            this.lastEventId = parseInt(event.lastEventId);
          } else if (data.id) {
            this.lastEventId = data.id;
          }
          
          this.trigger(data.event_type || 'message', data.payload || data);
          this.trigger('*', data); // Global listener
        } catch (err) {
          console.error('Failed to parse SSE payload:', err);
        }
      };

      // Custom Event listener for cached scale heartbeat updates
      this.eventSource.addEventListener('scale_heartbeat', (event: any) => {
        try {
          const data = JSON.parse(event.data);
          this.trigger('scale_heartbeat', data);
        } catch (err) {
          // Ignore parse errors on heartbeat comment
        }
      });

      this.eventSource.onerror = () => {
        this.handleDisconnect();
      };

    } catch (err) {
      console.error('Error establishing SSE connection:', err);
      this.handleDisconnect();
    }
  }

  /**
   * Handle connection dropped / errors.
   */
  private handleDisconnect() {
    this.cleanup();
    this.status.value = 'RECONNECTING';

    // Start fallback polling if not already running (polls overview state every 10s)
    if (!this.fallbackInterval) {
      this.status.value = 'FALLBACK';
      this.fallbackInterval = setInterval(async () => {
        try {
          const res = await axios.get('/api/dashboard/overview');
          this.trigger('fallback_sync', res.data.data);
        } catch (err) {
          console.warn('Fallback polling failed:', err);
        }
      }, 10000);
    }

    // Schedule connection retry with exponential backoff
    this.reconnectTimeout = setTimeout(() => {
      this.backoffTime = Math.min(this.backoffTime * 2, this.maxBackoff);
      this.connect(this.token);
    }, this.backoffTime);
  }

  /**
   * Subscribe to a specific real-time event.
   */
  public subscribe(eventType: string, callback: (data: any) => void) {
    if (!this.listeners[eventType]) {
      this.listeners[eventType] = [];
    }
    this.listeners[eventType].push(callback);
  }

  /**
   * Unsubscribe from a specific event.
   */
  public unsubscribe(eventType: string, callback: (data: any) => void) {
    const list = this.listeners[eventType];
    if (list) {
      this.listeners[eventType] = list.filter(cb => cb !== callback);
    }
  }

  /**
   * Trigger all callbacks registered for an event.
   */
  private trigger(eventType: string, data: any) {
    const list = this.listeners[eventType];
    if (list) {
      list.forEach(cb => {
        try {
          cb(data);
        } catch (err) {
          console.error(`Error executing callback for event ${eventType}:`, err);
        }
      });
    }
  }

  /**
   * Clean up connections and timers.
   */
  public disconnect() {
    this.cleanup();
    this.token = '';
    this.status.value = 'OFFLINE';
    if (this.fallbackInterval) {
      clearInterval(this.fallbackInterval);
      this.fallbackInterval = null;
    }
  }

  private cleanup() {
    if (this.eventSource) {
      this.eventSource.close();
      this.eventSource = null;
    }
    if (this.reconnectTimeout) {
      clearTimeout(this.reconnectTimeout);
      this.reconnectTimeout = null;
    }
  }
}

// Export as a single shared instance
export const realtimeService = new RealtimeClient();
export default realtimeService;
