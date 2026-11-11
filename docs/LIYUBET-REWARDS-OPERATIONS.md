# LiyubetBot rewards — deployment and operating guide

## What is implemented

- English-only Telegram flow; `/start` requires membership in the configured channel (production fails closed if the channel ID is missing).
- Main menu: **Sport | Casino** and **Referral | Support**, controlled under **Admin → Rewards → Reward Settings**.
- Unique `?start=ref_<internal-user-id>` links, immutable first-touch referral attribution for newly created Telegram bot users, and 2 ETB per qualifying referral (owner-configurable).
- A user qualifies after joining the bot for the first time **via a referral link**, and passing Telegram channel membership verification. Old users do not become eligible by opening a referral link later.
- Integer-cent wallet accounting with row locking, unique reward recipients, ledger records, suspended wallets, and owner-controlled adjustments.
- 50/100/500 ETB rewards redeemed as **one encrypted, uniquely assigned promo code** per successful withdrawal.
- Unavailable promo stock means no money is deducted. Tell the user to try again after restocking.
- Issued codes can be recovered in the user's Withdrawal History and viewed by the Owner after an audited reveal.
- Promo inventory uploaded from **Excel XLSX or CSV**: column 1 = `code`, optional column 2 = `amount` in ETB. Download the Excel template in **Rewards → Promo Codes**. Rows with malformed codes, sample placeholders, duplicates, or disallowed denominations are skipped.
- Backoffice: Referral Reports, Promo Codes, Withdrawal History, Player Wallets, Reward Settings. Financial controls are **Owner-only**.

## Production rollout (Laravel Cloud)

1. Back up the database and safeguard `APP_KEY`. Changing `APP_KEY` later makes encrypted promo codes unreadable.
2. Deploy the new LiyubetBot `main` branch, then run:

```sh
php artisan migrate --force
php artisan db:seed --force
php artisan optimize:clear
php artisan queue:restart
php artisan rewards:diagnose
```

Never use `migrate:fresh` on production: it destroys data.
3. Make sure PostgreSQL and Redis/Valkey are connected. Interactive queue workers must be running.
4. Under **Admin → Settings**, configure the exact channel ID (`-100...`) and channel invite URL. Promote the Telegram bot to channel administrator for reliable membership checks.
5. Under **Admin → Rewards → Reward Settings**, set your HTTPS Sport/Casino/Support URLs and menu labels, reward rate, denominations, and redemption account brand (**Kemerbet**).
6. Under **Admin → Rewards → Promo Codes**, download the example Excel sheet, replace every placeholder code with *real, unused, correctly-denominated provider codes*, then upload. Confirm available inventory per value.
7. Test `/start` from a *new* private Telegram account while not in the channel; it should show Join Channel and I've Joined. Join and verify; it should then show the 2×2 main menu and a referral link.
8. Test a genuine new user with referral link; verify only one 2 ETB credit, even after repeated `/start`. Test insufficient balance, stock shortage (unchanged balance), successful code delivery, history recovery, and backoffice reporting.

## Controls, cautions and limitations

- A *new Telegram user* is not independently a verified first-time **Kemerbet player**. Real player-account validation, age/eligibility checks, device identity, and anti-sybil safeguards require integration with your betting provider or an independent verification process **before** promising fraud-proof payouts.
- The code inventory is not verified against Kemerbet. Only upload promo codes created by your authorized provider and valid for the matching denominations.
- A code is considered issued when its withdrawal is committed to the database. If Telegram delivery fails, the balance is still deducted **but the code remains retrievable in Withdrawal History**; operators should review failed sends and assist via backoffice.
- Telegram does not provide a universal one-person-one-account identity guarantee. Owners can suspend suspicious wallets and review the ledger.
- Financial values are integer cents; referral awards apply only to new signups after the eligibility-cutoff migration.
- The top-level **Bot Content → Menus** resource manages legacy custom menus. The special 2×2 main menu is configured in Reward Settings to preserve the requested ordering.
- Do not share real promo codes, tokens, or `APP_KEY` in public GitHub, screenshots, error reports, or chat.
- Gambling-related promotions must follow applicable licensing, advertising restrictions and minimum-age requirements.
