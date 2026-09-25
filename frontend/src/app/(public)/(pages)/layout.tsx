/** Standard content pages: centered container on the light background. */
export default function ContentPagesLayout({ children }: LayoutProps<"/">) {
  return <main className="mx-auto w-full max-w-6xl flex-1 px-4 py-12">{children}</main>;
}
