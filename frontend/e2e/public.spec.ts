import { expect, test } from "@playwright/test";

test("landing page shows the ways to apply", async ({ page }) => {
  await page.goto("/");
  await expect(page.getByRole("heading", { level: 1 })).toContainText("Residence Card");
  await expect(page.getByRole("link", { name: "Apply for Residence Card" })).toBeVisible();
  await expect(page.getByRole("link", { name: /Track Application/ })).toBeVisible();
});

test("the site can be switched to French", async ({ page, isMobile }) => {
  await page.goto("/");
  if (isMobile) await page.getByRole("button", { name: "Open menu" }).click();
  await page.locator("header select").filter({ visible: true }).first().selectOption("fr");
  await expect(page.locator("html")).toHaveAttribute("lang", "fr");
  await expect(page.getByRole("heading", { level: 1 })).toContainText("carte de résident");
  await page.context().clearCookies();
});

test("verifying an unknown card says so", async ({ page }) => {
  await page.goto("/verify");
  await page.getByLabel("Card number").fill("999999");
  await page.getByLabel("Passport number").fill("X0000000");
  await page.getByRole("button", { name: "Verify" }).click();
  await expect(page.getByText(/No residence card matches/)).toBeVisible();
});

test("health checks answer for uptime monitors", async ({ request }) => {
  const response = await request.get("/api/health");
  expect(response.ok()).toBeTruthy();
  expect((await response.json()).api).toMatch(/ok|warn/);
});

test("the portal is installable", async ({ request }) => {
  const manifest = await (await request.get("/manifest.webmanifest")).json();
  expect(manifest.start_url).toBe("/portal");
  expect((await request.get("/sw.js")).ok()).toBeTruthy();
});
