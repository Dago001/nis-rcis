import { expect, test } from "@playwright/test";
import { STAFF_ID } from "./global-setup";
import { DEMO_PASSWORD, totp } from "./helpers";

test("an officer signs in with the authenticator and sees the approval queue", async ({ page }) => {
  await page.goto("/staff");
  await page.locator("#identifier").fill(STAFF_ID);
  await page.locator("#password").fill(DEMO_PASSWORD);
  await page.getByRole("button", { name: /sign in/i }).click();

  // First sign-in: set up the authenticator from the secret shown under the QR code.
  const secret = await page.locator("code.secret").innerText();
  await page.locator("#code").fill(totp(secret));
  await page.getByRole("button", { name: /sign in/i }).click();

  await expect(page).toHaveURL(/\/staff/);
  await expect(page.getByRole("heading", { name: "Dashboard" })).toBeVisible();
  await page.getByRole("link", { name: "Approval queue" }).click();
  await expect(page.getByRole("heading", { name: "Approval queue" })).toBeVisible();
  await expect(page.getByRole("link", { name: /^RC-\d{4}-\d{6}$/ }).first()).toBeVisible();
});
