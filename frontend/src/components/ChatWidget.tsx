"use client";

import Link from "next/link";
import { useEffect, useRef, useState, type FormEvent } from "react";
import { api, ApiError } from "@/lib/api-client";

type ChatLink = { label: string; href: string };
type ChatReply = { reply: string; source: string; links: ChatLink[]; conversation_id: string; suggestions: string[] };
type Message = { role: "user" | "assistant"; text: string; links?: ChatLink[] };

const MAX_LENGTH = 500;

/** Only same-site paths are ever rendered as links. */
function isInternal(href: string): boolean {
  return href.startsWith("/") && !href.startsWith("//") && !href.includes("\\");
}

/**
 * Floating help assistant. Messages go through the BFF to the Laravel API;
 * replies are rendered as plain text (never HTML), and only internal links
 * are clickable.
 */
export function ChatWidget({ scope }: { scope: "public" | "applicant" }) {
  const [open, setOpen] = useState(false);
  const [messages, setMessages] = useState<Message[]>([]);
  const [suggestions, setSuggestions] = useState<string[]>(
    scope === "applicant"
      ? ["What is the status of my application?", "When is my appointment?", "What documents do I need?"]
      : ["How do I apply?", "What documents do I need?", "How much does it cost?"],
  );
  const [input, setInput] = useState("");
  const [busy, setBusy] = useState(false);
  const conversationId = useRef<string | null>(null);
  const endRef = useRef<HTMLDivElement>(null);
  const inputRef = useRef<HTMLInputElement>(null);

  useEffect(() => {
    endRef.current?.scrollIntoView({ behavior: "smooth" });
  }, [messages, busy]);

  useEffect(() => {
    if (open) inputRef.current?.focus();
  }, [open]);

  async function send(text: string) {
    const message = text.trim().slice(0, MAX_LENGTH);
    if (!message || busy) return;

    setMessages((m) => [...m, { role: "user", text: message }]);
    setInput("");
    setBusy(true);
    try {
      const data = await api<ChatReply>(scope, "assistant", {
        method: "POST",
        json: { message, conversation_id: conversationId.current },
      });
      conversationId.current = data.conversation_id;
      setSuggestions(data.suggestions ?? []);
      setMessages((m) => [...m, { role: "assistant", text: data.reply, links: (data.links ?? []).filter((l) => isInternal(l.href)) }]);
    } catch (e) {
      const text =
        e instanceof ApiError && e.status === 429
          ? "You are sending messages too quickly. Please wait a minute and try again."
          : "Sorry, the assistant is unavailable right now. Please try again, or see the FAQ.";
      setMessages((m) => [...m, { role: "assistant", text, links: [{ label: "FAQ", href: "/faq" }] }]);
    } finally {
      setBusy(false);
    }
  }

  function onSubmit(e: FormEvent) {
    e.preventDefault();
    void send(input);
  }

  return (
    <div className="fixed bottom-4 right-4 z-50 flex flex-col items-end gap-3 print:hidden">
      {open && (
        <section
          role="dialog"
          aria-label="Residence card help assistant"
          className="flex h-[min(560px,calc(100vh-7rem))] w-[min(380px,calc(100vw-2rem))] flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl"
        >
          <header className="flex items-center justify-between bg-nis-primary px-4 py-3 text-white">
            <div>
              <p className="font-semibold">NIS Help Assistant</p>
              <p className="text-xs text-white/80">Residence card questions, answered instantly</p>
            </div>
            <button type="button" onClick={() => setOpen(false)} className="rounded p-1 hover:bg-white/15" aria-label="Close assistant">
              <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden>
                <path d="M18 6 6 18M6 6l12 12" />
              </svg>
            </button>
          </header>

          <div className="flex-1 space-y-3 overflow-y-auto bg-slate-50 p-4 text-sm" aria-live="polite">
            <div className="max-w-[85%] rounded-2xl rounded-tl-sm bg-white px-3 py-2 text-slate-700 shadow-sm">
              Hello! I can help with applying for, tracking and renewing a residence card
              {scope === "applicant" ? ", and tell you where your own application stands" : ""}. Please don&apos;t share passwords or payment details here.
            </div>

            {messages.map((m, i) => (
              <div key={i} className={m.role === "user" ? "flex justify-end" : ""}>
                <div
                  className={
                    m.role === "user"
                      ? "max-w-[85%] whitespace-pre-line break-words rounded-2xl rounded-tr-sm bg-nis-primary px-3 py-2 text-white"
                      : "max-w-[85%] whitespace-pre-line break-words rounded-2xl rounded-tl-sm bg-white px-3 py-2 text-slate-700 shadow-sm"
                  }
                >
                  {m.text}
                  {m.links && m.links.length > 0 && (
                    <div className="mt-2 flex flex-wrap gap-2">
                      {m.links.map((l) => (
                        <Link key={l.href} href={l.href} className="rounded-full border border-nis-primary/40 px-3 py-1 text-xs font-medium text-nis-primary hover:bg-nis-mint">
                          {l.label}
                        </Link>
                      ))}
                    </div>
                  )}
                </div>
              </div>
            ))}

            {busy && (
              <div className="inline-flex gap-1 rounded-2xl bg-white px-3 py-3 shadow-sm" aria-label="Assistant is typing">
                <span className="h-2 w-2 animate-bounce rounded-full bg-slate-400" />
                <span className="h-2 w-2 animate-bounce rounded-full bg-slate-400 [animation-delay:150ms]" />
                <span className="h-2 w-2 animate-bounce rounded-full bg-slate-400 [animation-delay:300ms]" />
              </div>
            )}
            <div ref={endRef} />
          </div>

          {suggestions.length > 0 && !busy && (
            <div className="flex gap-2 overflow-x-auto border-t border-slate-100 bg-white px-3 py-2">
              {suggestions.map((s) => (
                <button key={s} type="button" onClick={() => void send(s)} className="shrink-0 rounded-full bg-nis-mint px-3 py-1 text-xs text-nis-primary-dark hover:bg-nis-primary/15">
                  {s}
                </button>
              ))}
            </div>
          )}

          <form onSubmit={onSubmit} className="flex items-center gap-2 border-t border-slate-200 bg-white p-3">
            <label htmlFor="assistant-input" className="sr-only">Your question</label>
            <input
              ref={inputRef}
              id="assistant-input"
              value={input}
              onChange={(e) => setInput(e.target.value)}
              maxLength={MAX_LENGTH}
              placeholder="Ask a question…"
              autoComplete="off"
              className="flex-1 rounded-full border border-slate-300 px-4 py-2 text-sm outline-none focus:border-nis-primary focus:ring-2 focus:ring-nis-primary/20"
            />
            <button
              type="submit"
              disabled={busy || !input.trim()}
              className="flex h-9 w-9 items-center justify-center rounded-full bg-nis-primary text-white hover:bg-nis-primary-dark disabled:opacity-40"
              aria-label="Send"
            >
              <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden>
                <path d="M22 2 11 13M22 2l-7 20-4-9-9-4z" />
              </svg>
            </button>
          </form>
        </section>
      )}

      <button
        type="button"
        onClick={() => setOpen((o) => !o)}
        className="flex h-14 w-14 items-center justify-center rounded-full bg-nis-primary text-white shadow-lg transition hover:scale-105 hover:bg-nis-primary-dark focus:outline-none focus:ring-4 focus:ring-nis-primary/30"
        aria-label={open ? "Close help assistant" : "Open help assistant"}
        aria-expanded={open}
      >
        {open ? (
          <svg className="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden>
            <path d="M18 6 6 18M6 6l12 12" />
          </svg>
        ) : (
          <svg className="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden>
            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
            <path d="M8 10h.01M12 10h.01M16 10h.01" />
          </svg>
        )}
      </button>
    </div>
  );
}
