import { ref } from 'vue';

export type ScanSource = 'SCAN' | 'MANUAL' | 'SIMULATED';

export interface ScanResult {
  success: boolean;
  token: string;
  source: ScanSource;
  reason?: 'INVALID_FORMAT' | 'DUPLICATE';
}

const DUPLICATE_WINDOW_MS = 2000; // ignore the same token scanned twice within this window
const BUFFER_TIMEOUT_MS = 3000; // clear a stale, never-terminated wedge-scanner buffer

class ScannerService {
  private buffer: string = '';
  private lastKeyTime: number = 0;
  private bufferTimeoutHandle: ReturnType<typeof setTimeout> | null = null;
  private lastProcessedToken: string = '';
  private lastProcessedAt: number = 0;
  private callbacks: ((token: string, source: ScanSource) => void)[] = [];

  public lastScannedToken = ref<string>('');
  public lastScanSource = ref<ScanSource | null>(null);
  public lastResult = ref<ScanResult | null>(null);

  constructor() {
    if (typeof window !== 'undefined') {
      window.addEventListener('keydown', this.handleKeyDown.bind(this));
    }
  }

  /**
   * Register a scan callback. Fires for physical scans, manual-entry fallback, and simulated scans alike.
   */
  public onScan(callback: (token: string, source: ScanSource) => void) {
    this.callbacks.push(callback);
  }

  public offScan(callback: (token: string, source: ScanSource) => void) {
    this.callbacks = this.callbacks.filter(cb => cb !== callback);
  }

  private handleKeyDown(event: KeyboardEvent) {
    const currentTime = Date.now();

    // Wedge scanners fire keystrokes fast (<60ms apart); slower gaps indicate manual typing.
    const isFast = this.lastKeyTime === 0 || (currentTime - this.lastKeyTime) < 60;
    this.lastKeyTime = currentTime;

    if (!isFast && event.key !== 'Enter') {
      this.buffer = '';
    }

    if (event.key === 'Enter') {
      const scanned = this.buffer.trim();
      this.clearBuffer();

      if (!scanned) return;

      event.preventDefault();
      event.stopPropagation();
      this.processToken(scanned, 'SCAN');
    } else if (event.key.length === 1) {
      this.buffer += event.key;
      this.armBufferTimeout();
    }
  }

  private armBufferTimeout() {
    if (this.bufferTimeoutHandle) clearTimeout(this.bufferTimeoutHandle);
    this.bufferTimeoutHandle = setTimeout(() => {
      this.buffer = '';
      this.bufferTimeoutHandle = null;
    }, BUFFER_TIMEOUT_MS);
  }

  private clearBuffer() {
    this.buffer = '';
    if (this.bufferTimeoutHandle) {
      clearTimeout(this.bufferTimeoutHandle);
      this.bufferTimeoutHandle = null;
    }
  }

  /**
   * Shared validation + dedupe + dispatch pipeline for every entry point (physical scan, manual
   * fallback, and the dev/test simulator) so business logic downstream never has to care how the
   * token arrived.
   */
  private processToken(rawToken: string, source: ScanSource): ScanResult {
    const token = rawToken.trim();

    if (!token.startsWith('DF:')) {
      this.playBeep(800, 300); // error tone
      const result: ScanResult = { success: false, token, source, reason: 'INVALID_FORMAT' };
      this.lastResult.value = result;
      return result;
    }

    const now = Date.now();
    if (token === this.lastProcessedToken && (now - this.lastProcessedAt) < DUPLICATE_WINDOW_MS) {
      this.playBeep(1200, 80); // short neutral tone — acknowledged but not reprocessed
      const result: ScanResult = { success: false, token, source, reason: 'DUPLICATE' };
      this.lastResult.value = result;
      return result;
    }

    this.lastProcessedToken = token;
    this.lastProcessedAt = now;
    this.lastScannedToken.value = token;
    this.lastScanSource.value = source;
    this.playBeep(2000, 150); // success tone

    const result: ScanResult = { success: true, token, source };
    this.lastResult.value = result;

    this.callbacks.forEach(cb => {
      try {
        cb(token, source);
      } catch (err) {
        console.error('Scanner callback error:', err);
      }
    });

    return result;
  }

  /**
   * Production fallback for when the physical scanner is unavailable/malfunctioning: an operator
   * types or pastes the code themselves. Goes through the exact same validation/dedupe/dispatch
   * path as a real scan — callers never need a separate manual code path.
   */
  public submitManualEntry(rawInput: string): ScanResult {
    return this.processToken(rawInput, 'MANUAL');
  }

  public playBeep(frequency = 1500, duration = 100) {
    try {
      const context = new (window.AudioContext || (window as any).webkitAudioContext)();
      const oscillator = context.createOscillator();
      const gainNode = context.createGain();

      oscillator.type = 'sine';
      oscillator.frequency.value = frequency;

      gainNode.gain.setValueAtTime(0.1, context.currentTime);
      gainNode.gain.exponentialRampToValueAtTime(0.01, context.currentTime + duration / 1000);

      oscillator.connect(gainNode);
      gainNode.connect(context.destination);

      oscillator.start();
      setTimeout(() => {
        oscillator.stop();
        context.close();
      }, duration);
    } catch (err) {
      console.warn('Audio context beep failed:', err);
    }
  }

  /**
   * Dev/test convenience — same pipeline as submitManualEntry, tagged as SIMULATED so UI can
   * distinguish "picked from a mock dropdown" from a genuine manual-entry fallback if needed.
   */
  public simulateScan(token: string): ScanResult {
    return this.processToken(token, 'SIMULATED');
  }
}

export const scannerService = new ScannerService();
export default scannerService;
