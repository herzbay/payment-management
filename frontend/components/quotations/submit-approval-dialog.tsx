"use client";

import { useEffect, useState } from "react";
import {
  AlertCircle,
  CheckCircle2,
  ClipboardCheck,
  LoaderCircle,
  X,
} from "lucide-react";

import {
  approvalService,
  type Approver,
} from "@/lib/approval";

interface SubmitApprovalDialogProps {
  quotationId: number;
  quotationNumber: string;
  onClose: () => void;
  onSubmitted: () => void;
}

export default function SubmitApprovalDialog({
  quotationId,
  quotationNumber,
  onClose,
  onSubmitted,
}: SubmitApprovalDialogProps) {
  const [approvers, setApprovers] = useState<Approver[]>([]);
  const [approverId, setApproverId] = useState("");

  const [loadingApprovers, setLoadingApprovers] = useState(true);
  const [submitting, setSubmitting] = useState(false);

  const [error, setError] = useState("");
  const [success, setSuccess] = useState("");

  useEffect(() => {
    let mounted = true;

    async function loadApprovers() {
      setLoadingApprovers(true);
      setError("");

      try {
        const result = await approvalService.getApprovers();

        if (mounted) {
          setApprovers(result);
        }
      } catch (err) {
        if (mounted) {
          setError(
            err instanceof Error
              ? err.message
              : "Failed to load approvers.",
          );
        }
      } finally {
        if (mounted) {
          setLoadingApprovers(false);
        }
      }
    }

    void loadApprovers();

    return () => {
      mounted = false;
    };
  }, []);

  async function handleSubmit() {
    if (!approverId) {
      setError("Please select an approver.");
      return;
    }

    setSubmitting(true);
    setError("");

    try {
      await approvalService.submit(quotationId, Number(approverId));

      setSuccess("Quotation successfully submitted for approval.");

      onSubmitted();
    } catch (err) {
      setError(
        err instanceof Error
          ? err.message
          : "Failed to submit quotation for approval.",
      );
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
      onMouseDown={(event) => {
        if (event.target === event.currentTarget && !submitting) {
          onClose();
        }
      }}
    >
      <div className="w-full max-w-md rounded-xl border border-border bg-card shadow-xl">
        <div className="flex items-center justify-between border-b border-border p-5">
          <div className="flex items-center gap-3">
            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
              <ClipboardCheck className="h-5 w-5 text-primary" />
            </div>
            <div>
              <h2 className="font-semibold">Submit for Approval</h2>
              <p className="text-xs text-muted-foreground">
                Assign an approver to this quotation.
              </p>
            </div>
          </div>

          <button
            type="button"
            onClick={onClose}
            disabled={submitting}
            className="rounded-lg p-2 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground disabled:opacity-50"
            aria-label="Close dialog"
          >
            <X className="h-5 w-5" />
          </button>
        </div>

        <div className="space-y-5 p-5">
          <div className="rounded-lg bg-muted/50 p-3">
            <p className="text-xs text-muted-foreground">
              Quotation Number
            </p>
            <p className="mt-1 text-sm font-semibold">
              {quotationNumber}
            </p>
          </div>

          <div>
            <label
              htmlFor="approver"
              className="mb-2 block text-sm font-medium"
            >
              Select Approver <span className="text-red-500">*</span>
            </label>

            {loadingApprovers ? (
              <div className="flex h-11 items-center gap-2 rounded-lg border border-border px-3 text-sm text-muted-foreground">
                <LoaderCircle className="h-4 w-4 animate-spin" />
                Loading approvers...
              </div>
            ) : (
              <select
                id="approver"
                value={approverId}
                onChange={(event) => {
                  setApproverId(event.target.value);
                  setError("");
                }}
                disabled={submitting || approvers.length === 0}
                className="h-11 w-full rounded-lg border border-border bg-background px-3 text-sm outline-none focus:border-primary disabled:cursor-not-allowed disabled:opacity-60"
              >
                <option value="">Select an approver</option>
                {approvers.map((approver) => (
                  <option key={approver.id} value={approver.id}>
                    {approver.name} — {approver.role_name}
                  </option>
                ))}
              </select>
            )}

            {!loadingApprovers && approvers.length === 0 && !error && (
              <p className="mt-2 text-xs text-amber-600">
                No eligible approvers found. Please check user and role settings.
              </p>
            )}
          </div>

          {error && (
            <div className="flex items-start gap-2 rounded-lg border border-red-500/20 bg-red-500/5 p-3 text-sm text-red-600">
              <AlertCircle className="mt-0.5 h-4 w-4 shrink-0" />
              <p>{error}</p>
            </div>
          )}

          {success && (
            <div className="flex items-start gap-2 rounded-lg border border-emerald-500/20 bg-emerald-500/5 p-3 text-sm text-emerald-600">
              <CheckCircle2 className="mt-0.5 h-4 w-4 shrink-0" />
              <p>{success}</p>
            </div>
          )}

          <div className="flex justify-end gap-2 border-t border-border pt-4">
            <button
              type="button"
              onClick={onClose}
              disabled={submitting}
              className="h-10 rounded-lg border border-border px-4 text-sm font-medium transition-colors hover:bg-muted disabled:opacity-50"
            >
              Cancel
            </button>

            <button
              type="button"
              onClick={() => void handleSubmit()}
              disabled={
                submitting ||
                loadingApprovers ||
                !approverId ||
                approvers.length === 0
              }
              className="inline-flex h-10 items-center gap-2 rounded-lg bg-primary px-4 text-sm font-medium text-primary-foreground transition-opacity hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
            >
              {submitting && (
                <LoaderCircle className="h-4 w-4 animate-spin" />
              )}
              Submit for Approval
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
