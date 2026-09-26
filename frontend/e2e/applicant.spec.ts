import { expect, test } from "@playwright/test";

// Needs SKIP_EMAIL_VERIFICATION (the default when APP_ENV=local or testing).
test("a new applicant registers, signs in and starts an application", async ({ page }) => {
  const email = `e2e${Date.now()}@example.com`;
  await page.goto("/register");
  await page.getByLabel(/^Surname/).fill("Mensah");
  await page.getByLabel(/^Other names/).fill("Ama");
  await page.getByLabel(/^E-mail address/).fill(email);
  await page.locator('input[type="tel"]').fill("8031234567");
  await page.locator('input[autocomplete="new-password"]').first().fill("E2eTest2026");
  await page.locator('input[autocomplete="new-password"]').last().fill("E2eTest2026");
  await page.getByRole("checkbox").check();
  await page.getByRole("button", { name: "Create account" }).click();
  await expect(page.getByRole("status").or(page.getByRole("alert")).first()).toBeVisible();

  await page.goto("/portal");
  await page.locator("#identifier").fill(email);
  await page.locator("#password").fill("E2eTest2026");
  await page.getByRole("button", { name: "Sign in" }).click();
  await expect(page).toHaveURL(/\/portal/);
  await expect(page.getByRole("heading", { name: "Welcome, Ama" })).toBeVisible();

  await page.getByRole("link", { name: "New application" }).click();
  await expect(page.getByRole("heading", { name: /Step 1 of 7/ })).toBeVisible();
  await page.getByRole("button", { name: "Save & continue" }).click();
  await expect(page.getByText("Please correct the highlighted details.")).toBeVisible();
  await expect(page.getByText("Upload your passport photograph.")).toBeVisible();
});
