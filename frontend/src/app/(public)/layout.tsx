import { ChatWidget } from "@/components/ChatWidget";
import { FooterForPath } from "@/components/public/FooterForPath";
import { PublicHeader } from "@/components/public/PublicHeader";

export default function PublicLayout({ children }: LayoutProps<"/">) {
  return (
    <>
      <PublicHeader />
      {children}
      <FooterForPath />
      <ChatWidget scope="public" />
    </>
  );
}
