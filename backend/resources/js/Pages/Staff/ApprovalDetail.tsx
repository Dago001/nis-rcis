import React, { Component, type ReactNode } from "react";
import Link from "next/link";
import { StaffLayout } from "@/components/staff/StaffLayout";
import StaffApprovalDetailPage from "@/staff/approvals/[id]/page";
import { Alert } from "@/components/ui";

interface ErrorBoundaryProps {
  children: ReactNode;
}

interface ErrorBoundaryState {
  hasError: boolean;
  error: Error | null;
}

class ErrorBoundary extends Component<ErrorBoundaryProps, ErrorBoundaryState> {
  constructor(props: ErrorBoundaryProps) {
    super(props);
    this.state = { hasError: false, error: null };
  }

  static getDerivedStateFromError(error: Error): ErrorBoundaryState {
    return { hasError: true, error };
  }

  componentDidCatch(error: Error, errorInfo: React.ErrorInfo) {
    console.error("Staff Approval Detail caught render error:", error, errorInfo);
  }

  render() {
    if (this.state.hasError) {
      return (
        <div className="space-y-4 p-6">
          <div className="flex items-center gap-2 text-xs text-slate-500">
            <Link href="/staff/approvals" className="hover:text-nis-primary font-medium">← Back to Approvers</Link>
          </div>
          <Alert tone="danger">
            <div className="space-y-2">
              <div className="font-semibold text-base">Unable to Display Application Review</div>
              <p className="text-sm">
                {this.state.error?.message || "An unexpected error occurred while displaying the application."}
              </p>
              <div className="pt-2">
                <Link
                  href="/staff/approvals"
                  className="inline-flex items-center rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                >
                  Return to Approvers Queue
                </Link>
              </div>
            </div>
          </Alert>
        </div>
      );
    }
    return this.props.children;
  }
}

export default function ApprovalDetail(props: { id?: string | number }) {
  return (
    <StaffLayout>
      <ErrorBoundary>
        <StaffApprovalDetailPage id={props.id} />
      </ErrorBoundary>
    </StaffLayout>
  );
}
