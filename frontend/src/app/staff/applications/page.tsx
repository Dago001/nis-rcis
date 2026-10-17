"use client";

import { ApplicationsQueue } from "@/components/ApplicationsQueue";

export default function VettingPage() {
  return (
    <ApplicationsQueue
      pageTitle="Vetting"
      pageSubtitle="Review and vet online and assisted residence card applications"
      basePath="/staff/applications"
    />
  );
}
