'use client';

import { useCallback, useRef, useState } from 'react';

/* eslint-disable @typescript-eslint/no-explicit-any */

/**
 * Web Speech API 音声入力フック（旧アプリの音声入力機能を再現）
 */
export function useVoiceInput() {
  const [activeField, setActiveField] = useState<string | null>(null);
  const recognitionRef = useRef<any>(null);

  const startVoiceInput = useCallback(
    (fieldId: string, setValue: (val: string) => void, currentValue: string, continuous = true) => {
      if (typeof window === 'undefined') return;

      const w = window as any;
      const SpeechRecognitionCtor = w.SpeechRecognition || w.webkitSpeechRecognition;

      if (!SpeechRecognitionCtor) {
        alert('お使いのブラウザは音声入力に対応していません。Chrome、Edge、Safariをご利用ください。');
        return;
      }

      // 既に同じフィールドで聞いている場合は停止
      if (activeField === fieldId && recognitionRef.current) {
        recognitionRef.current.stop();
        setActiveField(null);
        return;
      }

      // 前の認識を停止
      if (recognitionRef.current) {
        try { recognitionRef.current.stop(); } catch { /* ignore */ }
      }

      const recognition = new SpeechRecognitionCtor();
      recognition.lang = 'ja-JP';
      recognition.continuous = continuous;
      recognition.interimResults = continuous;
      recognitionRef.current = recognition;
      setActiveField(fieldId);

      let finalTranscript = currentValue;

      recognition.onresult = (event: any) => {
        if (continuous) {
          let interimTranscript = '';
          for (let i = event.resultIndex; i < event.results.length; i++) {
            const transcript = event.results[i][0].transcript;
            if (event.results[i].isFinal) {
              finalTranscript += transcript;
            } else {
              interimTranscript += transcript;
            }
          }
          setValue(finalTranscript + interimTranscript);
        } else {
          const transcript = event.results[0][0].transcript;
          setValue(transcript);
        }
      };

      recognition.onerror = (event: any) => {
        console.error('音声認識エラー:', event.error);
        setActiveField(null);
        if (event.error === 'no-speech') {
          alert('音声が認識されませんでした。もう一度お試しください。');
        } else if (event.error !== 'aborted') {
          alert('音声入力エラー: ' + event.error);
        }
      };

      recognition.onend = () => {
        setActiveField(null);
        recognitionRef.current = null;
      };

      recognition.start();
    },
    [activeField]
  );

  const stopVoiceInput = useCallback(() => {
    if (recognitionRef.current) {
      try { recognitionRef.current.stop(); } catch { /* ignore */ }
      setActiveField(null);
    }
  }, []);

  return { activeField, startVoiceInput, stopVoiceInput };
}
