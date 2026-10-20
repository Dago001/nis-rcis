"use client";

import Link from "next/link";
import { useEffect, useRef, useState, type FormEvent } from "react";
import { api, ApiError } from "@/lib/api-client";
import { useI18n } from "@/components/I18nProvider";

type ChatLink = { label: string; href: string };
type ChatReply = { reply: string; source: string; links: ChatLink[]; conversation_id: string; suggestions: string[] };
type Message = { role: "user" | "assistant"; text: string; links?: ChatLink[] };

const MAX_LENGTH = 500;

interface QuickCategory {
  id: string;
  name: { en: string; fr: string };
  questions: { en: string; fr: string }[];
}

const QUICK_CATEGORIES: QuickCategory[] = [
  {
    id: "general",
    name: {
      en: "General & Eligibility",
      fr: "Général & Éligibilité",
    },
    questions: [
      {
        en: "Who is eligible for an ECOWAS Residence Card?",
        fr: "Qui est éligible pour une Carte de Résidence CEDEAO ?",
      },
      {
        en: "What are the key benefits of holding this card?",
        fr: "Quels sont les principaux avantages de détenir cette carte ?",
      },
      {
        en: "What is the validity period of the residence card?",
        fr: "Quelle est la durée de validité de la carte de résidence ?",
      },
    ],
  },
  {
    id: "categories_docs",
    name: {
      en: "Application Categories & Documents",
      fr: "Catégories & Documents requis",
    },
    questions: [
      {
        en: "What documents are required for Employment / Work Category?",
        fr: "Quels documents sont requis pour la catégorie Emploi / Travail ?",
      },
      {
        en: "What documents are required for Business / Investor Category?",
        fr: "Quels documents sont requis pour la catégorie Affaires / Investisseur ?",
      },
      {
        en: "What documents are required for Students in Nigeria?",
        fr: "Quels documents sont requis pour les Étudiants au Nigeria ?",
      },
      {
        en: "What documents are required for Religious Activities?",
        fr: "Quels documents sont requis pour les Activités Religieuses ?",
      },
    ],
  },
  {
    id: "renewals_cod",
    name: {
      en: "Renewals & Change of Data",
      fr: "Renouvellement & Changement de données",
    },
    questions: [
      {
        en: "How do I renew my ECOWAS Residence Card?",
        fr: "Comment renouveler ma Carte de Résidence CEDEAO ?",
      },
      {
        en: "Can I update my details during renewal?",
        fr: "Puis-je modifier mes informations lors du renouvellement ?",
      },
      {
        en: "What is the fee for Change of Data (Name / Date of Birth)?",
        fr: "Quel est le tarif pour le Changement de Données (Nom / Date de naissance) ?",
      },
    ],
  },
  {
    id: "dependants",
    name: {
      en: "Dependants",
      fr: "Personnes à charge",
    },
    questions: [
      {
        en: "Can I add my spouse or children as dependants?",
        fr: "Puis-je ajouter mon conjoint ou mes enfants comme personnes à charge ?",
      },
      {
        en: "What documents are required for dependant applications?",
        fr: "Quels documents sont requis pour les demandes de personnes à charge ?",
      },
    ],
  },
  {
    id: "fees_payments",
    name: {
      en: "Fees & Payments",
      fr: "Frais & Paiements",
    },
    questions: [
      {
        en: "How much does the ECOWAS Residence Card cost?",
        fr: "Combien coûte la Carte de Résidence CEDEAO ?",
      },
      {
        en: "How do I make payment for my application?",
        fr: "Comment effectuer le paiement de ma demande ?",
      },
    ],
  },
  {
    id: "appointments_biometrics",
    name: {
      en: "Appointments & Biometrics",
      fr: "Rendez-vous & Biométrie",
    },
    questions: [
      {
        en: "How do I schedule or reschedule my biometrics appointment?",
        fr: "Comment planifier ou modifier mon rendez-vous biométrique ?",
      },
      {
        en: "What should I bring to the NIS State Command for biometrics?",
        fr: "Que dois-je apporter au Commandement d'État de la NIS pour la biométrie ?",
      },
    ],
  },
  {
    id: "status_collection",
    name: {
      en: "Application Status & Card Collection",
      fr: "Suivi du statut & Retrait de carte",
    },
    questions: [
      {
        en: "How can I track the status of my application?",
        fr: "Comment puis-je suivre l'état de ma demande ?",
      },
      {
        en: "How will I know when my residence card is ready for collection?",
        fr: "Comment saurai-je quand ma carte de résidence est prête à être retirée ?",
      },
    ],
  },
];

/** Only same-site paths are ever rendered as links. */
function isInternal(href: string): boolean {
  return href.startsWith("/") && !href.startsWith("//") && !href.includes("\\");
}

function resolveChatLink(link: ChatLink, scope: "public" | "applicant"): ChatLink {
  if (scope === "public") {
    const l = (link.label || "").toLowerCase();
    const h = (link.href || "").toLowerCase();
    if (h.includes("renewal") || l.includes("renew")) {
      return { ...link, href: "/login/applicant" };
    }
    if (
      h.startsWith("/portal/apply") ||
      h.includes("apply") ||
      l.includes("apply") ||
      l.includes("account") ||
      l.includes("register")
    ) {
      return { ...link, href: "/register" };
    }
  }
  return link;
}

/**
 * Floating ECOWAS Residence Card Assistant. Messages go through the BFF to the Laravel API;
 * replies are rendered as plain text (never HTML), and only internal links
 * are clickable.
 */
export function ChatWidget({ scope }: { scope: "public" | "applicant" }) {
  const { lang, t } = useI18n();
  const isFrench = lang === "fr";

  const [open, setOpen] = useState(false);
  const [messages, setMessages] = useState<Message[]>([]);
  const [showQuestionsMenu, setShowQuestionsMenu] = useState(false);
  const [selectedCategory, setSelectedCategory] = useState<string | null>("general");
  const [suggestions, setSuggestions] = useState<string[]>([
    isFrench ? "Quels documents sont requis ?" : "What documents are required?",
    isFrench ? "Comment renouveler ma carte ?" : "How do I renew my card?",
    isFrench ? "Combien coûte la carte ?" : "How much does it cost?",
    isFrench ? "Puis-je ajouter mes enfants ?" : "Can I add dependants?",
  ]);
  const [input, setInput] = useState("");
  const [busy, setBusy] = useState(false);
  const conversationId = useRef<string | null>(null);
  const endRef = useRef<HTMLDivElement>(null);
  const inputRef = useRef<HTMLInputElement>(null);

  // Listen for custom trigger to open chat from anywhere (e.g. landing page footer button)
  useEffect(() => {
    function handleOpenChat() {
      setOpen(true);
      setTimeout(() => inputRef.current?.focus(), 150);
    }
    window.addEventListener("nis-open-chat", handleOpenChat);
    return () => window.removeEventListener("nis-open-chat", handleOpenChat);
  }, []);

  useEffect(() => {
    endRef.current?.scrollIntoView({ behavior: "smooth" });
  }, [messages, busy]);

  useEffect(() => {
    if (open) inputRef.current?.focus();
  }, [open]);

  async function send(text: string) {
    const message = text.trim().slice(0, MAX_LENGTH);
    if (!message || busy) return;

    setShowQuestionsMenu(false);
    setMessages((m) => [...m, { role: "user", text: message }]);
    setInput("");
    setBusy(true);
    try {
      const data = await api<ChatReply>(scope, "assistant", {
        method: "POST",
        json: { message, conversation_id: conversationId.current },
      });
      conversationId.current = data.conversation_id;
      if (data.suggestions && data.suggestions.length > 0) {
        setSuggestions(data.suggestions);
      }
      setMessages((m) => [
        ...m,
        {
          role: "assistant",
          text: data.reply,
          links: (data.links ?? [])
            .filter((l) => isInternal(l.href))
            .map((l) => resolveChatLink(l, scope)),
        },
      ]);
    } catch (e) {
      const text =
        e instanceof ApiError && e.status === 429
          ? isFrench
            ? "Vous envoyez des messages trop rapidement. Veuillez patienter une minute avant de réessayer."
            : "You are sending messages too quickly. Please wait a minute and try again."
          : isFrench
            ? "Désolé, l'assistant est temporairement indisponible. Veuillez réessayer ou consulter la FAQ."
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

  const welcomeText = isFrench
    ? "Bienvenue sur le portail de la Carte de Résidence CEDEAO. Je peux vous aider à comprendre les conditions requises, les catégories de cartes de résidence, les renouvellements, les demandes pour personnes à charge, les paiements, les rendez-vous et les procédures de demande. Comment puis-je vous aider ?"
    : "Welcome to the ECOWAS Residence Card Portal. I can help you understand application requirements, residence card categories, renewals, dependant applications, payments, appointments and application procedures. How may I assist you?";

  return (
    <div className="fixed bottom-4 right-4 z-50 flex flex-col items-end gap-3 print:hidden">
      {open && (
        <section
          role="dialog"
          aria-label={isFrench ? "Assistant virtuel Carte de Résidence CEDEAO" : "ECOWAS Residence Card Assistant"}
          className="flex h-[min(600px,calc(100vh-6rem))] w-[min(420px,calc(100vw-1.5rem))] flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl transition-all"
        >
          {/* Header */}
          <header className="flex items-center justify-between bg-nis-primary px-4 py-3 text-white">
            <div className="flex items-center gap-2.5">
              <div className="flex h-9 w-9 items-center justify-center rounded-full bg-white/15 text-white font-bold">
                <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                  <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
                  <path d="M8 10h.01M12 10h.01M16 10h.01" />
                </svg>
              </div>
              <div>
                <p className="font-semibold leading-tight text-white">
                  {isFrench ? "Assistant Carte de Résidence CEDEAO" : "ECOWAS Residence Card Assistant"}
                </p>
                <p className="text-xs text-white/80">
                  {isFrench ? "Comment pouvons-nous vous aider aujourd'hui ?" : "How may we assist you today?"}
                </p>
              </div>
            </div>
            <div className="flex items-center gap-1">
              <button
                type="button"
                onClick={() => setShowQuestionsMenu((prev) => !prev)}
                className={`rounded px-2 py-1 text-xs font-medium transition ${
                  showQuestionsMenu ? "bg-white text-nis-primary font-semibold" : "bg-white/15 text-white hover:bg-white/25"
                }`}
                title={isFrench ? "Afficher les questions rapides" : "Browse Quick Questions"}
              >
                {showQuestionsMenu ? (isFrench ? "Discussion" : "Chat") : (isFrench ? "Questions FAQ" : "Quick Topics")}
              </button>
              <button
                type="button"
                onClick={() => setOpen(false)}
                className="rounded p-1 hover:bg-white/15 transition text-white"
                aria-label="Close assistant"
              >
                <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden>
                  <path d="M18 6 6 18M6 6l12 12" />
                </svg>
              </button>
            </div>
          </header>

          {/* Quick Questions Categorized Overlay / Drawer */}
          {showQuestionsMenu ? (
            <div className="flex flex-1 flex-col overflow-hidden bg-slate-50">
              <div className="border-b border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700">
                {isFrench ? "Questions fréquentes par catégorie (18 sujets)" : "Frequently Asked Questions by Category (18 Topics)"}
              </div>
              {/* Category tabs */}
              <div className="flex overflow-x-auto border-b border-slate-200 bg-slate-100 p-1.5 gap-1">
                {QUICK_CATEGORIES.map((cat) => {
                  const isActive = selectedCategory === cat.id;
                  return (
                    <button
                      key={cat.id}
                      type="button"
                      onClick={() => setSelectedCategory(cat.id)}
                      className={`shrink-0 rounded-lg px-2.5 py-1.5 text-xs font-medium transition ${
                        isActive
                          ? "bg-nis-primary text-white shadow-sm"
                          : "bg-white text-slate-700 hover:bg-slate-200 border border-slate-200"
                      }`}
                    >
                      {cat.name[isFrench ? "fr" : "en"]}
                    </button>
                  );
                })}
              </div>
              {/* Questions list for selected category */}
              <div className="flex-1 overflow-y-auto p-3 space-y-2">
                {QUICK_CATEGORIES.find((c) => c.id === selectedCategory)?.questions.map((q, qIdx) => {
                  const questionText = q[isFrench ? "fr" : "en"];
                  return (
                    <button
                      key={qIdx}
                      type="button"
                      onClick={() => void send(questionText)}
                      className="w-full text-left rounded-xl border border-slate-200 bg-white p-3 text-xs font-medium text-slate-800 shadow-sm transition hover:border-nis-primary hover:bg-nis-mint/40 hover:text-nis-primary flex items-start gap-2 group"
                    >
                      <span className="mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-nis-mint text-[10px] text-nis-primary group-hover:bg-nis-primary group-hover:text-white">
                        ?
                      </span>
                      <span className="leading-snug">{questionText}</span>
                    </button>
                  );
                })}
              </div>
            </div>
          ) : (
            /* Chat message thread */
            <div className="flex-1 space-y-3 overflow-y-auto bg-slate-50 p-4 text-sm" aria-live="polite">
              {/* Official Welcome Message */}
              <div className="max-w-[90%] rounded-2xl rounded-tl-sm bg-white p-3.5 text-slate-800 shadow-sm border border-slate-100 text-xs sm:text-sm leading-relaxed">
                <p className="font-semibold text-nis-primary-dark mb-1">
                  {isFrench ? "Assistance Officielle NIS" : "Official NIS Assistant"}
                </p>
                <p>{welcomeText}</p>
                <div className="mt-2.5 pt-2 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
                  <span>{isFrench ? "Disponible 24h/24, 7j/7" : "Available 24/7"}</span>
                  <button
                    type="button"
                    onClick={() => setShowQuestionsMenu(true)}
                    className="font-medium text-nis-primary hover:underline inline-flex items-center gap-1"
                  >
                    <span>{isFrench ? "Parcourir les questions" : "Browse all questions"}</span>
                    &rarr;
                  </button>
                </div>
              </div>

              {messages.map((m, i) => (
                <div key={i} className={m.role === "user" ? "flex justify-end" : ""}>
                  <div
                    className={
                      m.role === "user"
                        ? "max-w-[85%] whitespace-pre-line break-words rounded-2xl rounded-tr-sm bg-nis-primary px-3.5 py-2.5 text-xs sm:text-sm text-white shadow-sm"
                        : "max-w-[90%] whitespace-pre-line break-words rounded-2xl rounded-tl-sm bg-white p-3.5 text-xs sm:text-sm text-slate-700 shadow-sm border border-slate-100 leading-relaxed"
                    }
                  >
                    {m.text}
                    {m.links && m.links.length > 0 && (
                      <div className="mt-2.5 flex flex-wrap gap-2 pt-1 border-t border-slate-100">
                        {m.links.map((l, lIdx) => {
                          const resolved = resolveChatLink(l, scope);
                          return (
                            <Link
                              key={`${resolved.href}-${lIdx}`}
                              href={resolved.href}
                              className="inline-flex items-center gap-1 rounded-full border border-nis-primary/40 bg-nis-mint/60 px-3 py-1 text-xs font-semibold text-nis-primary hover:bg-nis-primary hover:text-white transition"
                            >
                              <span>{resolved.label}</span>
                              <span aria-hidden>&rarr;</span>
                            </Link>
                          );
                        })}
                      </div>
                    )}
                  </div>
                </div>
              ))}

              {busy && (
                <div className="inline-flex items-center gap-1.5 rounded-2xl bg-white px-3.5 py-2.5 shadow-sm border border-slate-100" aria-label="Assistant is typing">
                  <span className="text-xs text-slate-500 font-medium mr-1">
                    {isFrench ? "Réflexion en cours" : "Searching guidelines"}
                  </span>
                  <span className="h-1.5 w-1.5 animate-bounce rounded-full bg-nis-primary" />
                  <span className="h-1.5 w-1.5 animate-bounce rounded-full bg-nis-primary [animation-delay:150ms]" />
                  <span className="h-1.5 w-1.5 animate-bounce rounded-full bg-nis-primary [animation-delay:300ms]" />
                </div>
              )}
              <div ref={endRef} />
            </div>
          )}

          {/* Quick suggestions pills */}
          {!showQuestionsMenu && suggestions.length > 0 && !busy && (
            <div className="flex gap-1.5 overflow-x-auto border-t border-slate-100 bg-white px-3 py-2 scrollbar-none">
              {suggestions.map((s) => (
                <button
                  key={s}
                  type="button"
                  onClick={() => void send(s)}
                  className="shrink-0 rounded-full border border-nis-mint bg-nis-mint/50 px-2.5 py-1 text-xs text-nis-primary-dark hover:bg-nis-primary hover:text-white transition font-medium"
                >
                  {s}
                </button>
              ))}
            </div>
          )}

          {/* Input form */}
          <form onSubmit={onSubmit} className="flex items-center gap-2 border-t border-slate-200 bg-white p-3">
            <label htmlFor="assistant-input" className="sr-only">
              {isFrench ? "Votre question" : "Your question"}
            </label>
            <input
              ref={inputRef}
              id="assistant-input"
              value={input}
              onChange={(e) => setInput(e.target.value)}
              maxLength={MAX_LENGTH}
              placeholder={isFrench ? "Posez une question sur la carte..." : "Ask a question about the card…"}
              autoComplete="off"
              className="flex-1 rounded-full border border-slate-300 px-4 py-2 text-xs sm:text-sm outline-none focus:border-nis-primary focus:ring-2 focus:ring-nis-primary/20"
            />
            <button
              type="submit"
              disabled={busy || !input.trim()}
              className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-nis-primary text-white hover:bg-nis-primary-dark disabled:opacity-40 transition shadow-sm"
              aria-label={isFrench ? "Envoyer" : "Send"}
            >
              <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden>
                <path d="M22 2 11 13M22 2l-7 20-4-9-9-4z" />
              </svg>
            </button>
          </form>
        </section>
      )}

      {/* Floating launcher button */}
      <button
        type="button"
        onClick={() => setOpen((o) => !o)}
        className="flex h-14 w-14 items-center justify-center rounded-full bg-nis-primary text-white shadow-xl transition hover:scale-105 hover:bg-nis-primary-dark focus:outline-none focus:ring-4 focus:ring-nis-primary/30"
        aria-label={open ? (isFrench ? "Fermer l'assistant" : "Close help assistant") : (isFrench ? "Ouvrir l'assistant" : "Open help assistant")}
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
