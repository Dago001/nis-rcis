import { ChatWidget } from "@/components/ChatWidget";
import { PublicFooter } from "@/components/public/PublicFooter";
import { PublicHeader } from "@/components/public/PublicHeader";

export default function PublicLayout({ children }: LayoutProps<"/">) {
  return (
    <>
      <PublicHeader />
      {children}
      <PublicFooter />
      <ChatWidget scope="public" />
    </>
  );
}
