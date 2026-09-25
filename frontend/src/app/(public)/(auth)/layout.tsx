import Image from "next/image";

/** Sign-in and registration pages: full-page NIS photograph behind the form. */
export default function AuthPagesLayout({ children }: LayoutProps<"/">) {
  return (
    <main className="relative flex flex-1 items-center overflow-hidden px-4 py-12 sm:py-16">
      <Image src="/images/hq-dusk.jpg" alt="" fill priority sizes="100vw" className="object-cover" />
      <div className="absolute inset-0 bg-gradient-to-br from-black/75 via-nis-primary-dark/60 to-black/60" aria-hidden />
      <div className="relative z-10 mx-auto w-full max-w-6xl">{children}</div>
    </main>
  );
}
