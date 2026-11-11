# Telegram Menu button + persistent chat keyboard

Telegram has three different interface elements:

1. **START** — Telegram automatically shows a Start control the first time a person opens the bot. Telegram controls when this appears.
2. **Menu** — the native command menu icon near the chat text field, installed globally with `telegram:setup-menu`. It contains `/start`, `/menu`, `/sport`, `/casino`, `/referral`, `/balance`, `/withdraw`, `/history`, `/support`, `/help`.
3. **Reply keyboard** — persistent buttons provided by the bot after a verified member sends `/start`. They appear where the typing keyboard is shown, including **Sport | Casino**, **Referral | Support**, **Start | Balance**, **Withdraw | History**. Telegram users can switch between typing and the custom keyboard depending on their client.

## Enable on LiyubetBot

Deploy the latest `main` code and run this Laravel Cloud command:

```bash
php artisan telegram:setup-menu
```

Then open `@liyubetsbot` and send `/start`. Joining `https://t.me/liyubet1` is still required first. Channel ID must be `-1003512499500` in **Admin > Settings**; the bot must be a channel admin.

Commands do not bypass the mandatory channel join gate. The persistent keyboard uses the button labels in Reward Settings, and the Telegram handlers interpret those text messages as menu actions, rather than accidental support inquiries.

The exact placement and keyboard visibility depend on the Telegram client. The initial START button is not something a bot can force back after the user has started chatting.
