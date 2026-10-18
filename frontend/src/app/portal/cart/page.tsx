"use client";

import Link from "next/link";
import { PageTitle } from "@/components/PageTitle";
import { StatusBadge } from "@/components/StatusBadge";
import { Alert, Button, Panel, Spinner } from "@/components/ui";
import { naira } from "@/lib/format";
import { useFetch } from "@/lib/use-fetch";
import { useI18n } from "@/components/I18nProvider";

type CartItem = {
  id: string;
  type: "DRAFT" | "APPLICATION";
  reference: string;
  category: string;
  application_type: string;
  description: string;
  amount_naira: number;
  status: string;
  action_url: string;
  created_at: string | null;
};

type CartResponse = {
  items: CartItem[];
  total_naira: number;
  currency: string;
  count: number;
};

export default function CartPage() {
  const { data, error, loading, reload } = useFetch<CartResponse>("applicant", "cart");
  const { t } = useI18n();

  return (
    <div className="space-y-6">
      <PageTitle
        eyebrow={t("Applicant console")}
        title={t("My Cart")}
        subtitle={t("Review pending applications and complete online fee payment securely.")}
      />

      {error && <Alert tone="danger">{error}</Alert>}

      <Panel title={t("Pending Payment Items")}>
        {loading || !data ? (
          <div className="py-8 flex justify-center">
            <Spinner />
          </div>
        ) : data.items.length === 0 ? (
          <div className="py-10 text-center">
            <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-slate-100 text-slate-400">
              <svg className="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
              </svg>
            </div>
            <h3 className="mt-3 text-sm font-semibold text-slate-900">{t("Your cart is empty")}</h3>
            <p className="mt-1 text-xs text-slate-500 max-w-sm mx-auto">
              {t("Your cart is empty. You have no pending payments.")}
            </p>
            <div className="mt-5 flex items-center justify-center gap-3">
              <Link
                href="/portal/apply"
                className="inline-flex items-center rounded-lg bg-nis-green px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-nis-green/90 transition"
              >
                {t("Start New Application")}
              </Link>
              <Link
                href="/portal"
                className="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition"
              >
                {t("View My Applications")}
              </Link>
            </div>
          </div>
        ) : (
          <div className="space-y-6">
            <div className="overflow-x-auto rounded-lg border border-slate-200">
              <table className="w-full text-left text-sm">
                <thead>
                  <tr className="border-b border-slate-200 bg-slate-50/80 text-xs font-semibold uppercase tracking-wider text-slate-600">
                    <th scope="col" className="px-4 py-3 text-center w-12">{t("S/N")}</th>
                    <th scope="col" className="px-4 py-3 whitespace-nowrap">{t("Application Reference")}</th>
                    <th scope="col" className="px-4 py-3 whitespace-nowrap">{t("Description / Category")}</th>
                    <th scope="col" className="px-4 py-3 whitespace-nowrap">{t("Amount")}</th>
                    <th scope="col" className="px-4 py-3 whitespace-nowrap">{t("Status")}</th>
                    <th scope="col" className="px-4 py-3 text-right whitespace-nowrap">{t("Action")}</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100 bg-white">
                  {data.items.map((item, idx) => (
                    <tr key={item.id} className="hover:bg-slate-50/60 transition-colors">
                      <td className="px-4 py-3.5 text-center text-xs font-medium text-slate-500">{idx + 1}</td>
                      <td className="px-4 py-3.5 whitespace-nowrap">
                        <span className="font-semibold text-slate-800">{item.reference}</span>
                        <span className="block text-[11px] text-slate-500 uppercase">{item.application_type}</span>
                      </td>
                      <td className="px-4 py-3.5 whitespace-nowrap">
                        <div className="font-medium text-slate-700">{item.description}</div>
                      </td>
                      <td className="px-4 py-3.5 whitespace-nowrap font-semibold text-slate-900">
                        {naira(item.amount_naira)}
                      </td>
                      <td className="px-4 py-3.5 whitespace-nowrap">
                        <StatusBadge status="PENDING" label={t("Pending Payment")} />
                      </td>
                      <td className="px-4 py-3.5 text-right whitespace-nowrap">
                        <Link
                          href={item.action_url}
                          className="inline-flex items-center gap-1.5 rounded-md bg-nis-green px-3.5 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-nis-green/90 transition"
                        >
                          <span>{t("Proceed to Payment")}</span>
                          <span>→</span>
                        </Link>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            {/* Cart Summary */}
            <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 rounded-xl border border-slate-200 bg-slate-50 p-4 sm:p-5">
              <div>
                <div className="text-xs font-medium uppercase tracking-wider text-slate-500">{t("Total Payable")}</div>
                <div className="text-2xl font-bold text-slate-900">{naira(data.total_naira)}</div>
                <p className="mt-0.5 text-xs text-slate-500">
                  {t("Processing fee: ₦0.00 · Secure payment handled via Paystack")}
                </p>
              </div>

              {data.items[0] && (
                <Link
                  href={data.items[0].action_url}
                  className="inline-flex items-center justify-center rounded-lg bg-nis-orange px-6 py-2.5 text-sm font-semibold text-white shadow-sm hover:brightness-95 transition w-full sm:w-auto"
                >
                  {t("Checkout / Pay Now")}
                </Link>
              )}
            </div>
          </div>
        )}
      </Panel>
    </div>
  );
}
