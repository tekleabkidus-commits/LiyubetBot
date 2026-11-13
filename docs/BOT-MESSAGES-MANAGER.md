# LiyubetBot — Bot Messages manager

## Page

**Admin → Bot Content → Bot Messages** (`/admin/bot-messages`).

This is the dedicated editor for all fixed bot-to-user chat messages. It is grouped by onboarding, referral, redemptions, navigation, and preferences. Choose one message; edit its body; optionally attach or remove an image; and save that message only. Existing messages are unaffected. Only the Owner can edit sensitive bot-wide messages (including promo codes); marketers and viewers cannot access this centralized editor.

Images accept JPEG, PNG and WebP up to 6 MB. They use the existing `media_files` table and Telegram `file_id` reuse. If the rendered text is over Telegram's 1024-character caption limit, the system sends the image separately followed by the full message with its keyboard. Failed image delivery falls back to sending the text.

**Important for Laravel Cloud:** File uploads must be stored on a durable shared disk such as S3-compatible storage; the default ephemeral local filesystem may lose uploads after deploy/restart. Set `FILESYSTEM_DISK` and provide cloud storage credentials through secure environment variables. Test image delivery before launch.

## Two-message welcome

- **Welcome message** is sent on `/start` after channel membership; it retains the current live welcome copy until you edit and save it.
- **Second referral-promotion message** is a separate independently editable message with its own image. It is **disabled by default** and will not be sent until the operator enables it. Previously proposed iPhone/prize claims have NOT been published.
- Place `{first_name}` in the welcome text and `{referral_link}` in the second message. Both English and Amharic can be written in the same body.
- The main Telegram reply keyboard still appears with the first welcome.

## Other editors

- **Bot Content → Keyword Replies:** Custom keyword and any-message responses, each already supports media upload.
- **Bot Content → Menus:** Custom menu content.
- **Rewards → Reward Settings:** Button labels/URLs and referral rules.
- Campaign and automation-specific messages are edited in their existing campaign/journey screens.

Telegram callback toast labels and system control captions (e.g. 'I've Joined') are not standalone chat messages; they are not images and remain controlled by Telegram/system UI. Dynamic referral amounts, wallet balances and codes are populated from real records via tokens rather than hard-coded into copy.

## Deployment

Deploy the latest main branch. No new database migrations are required: message content is stored under existing JSON settings keys `bot.messages.<message-key>`. The existing edited welcome value is inherited until the new page saves it.

```sh
php artisan optimize:clear
php artisan queue:restart
```

Verify: open `/admin/bot-messages`, edit an innocuous test message and attach an image, send the relevant bot command, confirm the body and image, and then restore it. Check `APP_KEY`, storage, and Telegram worker logs when testing.
