# Automation images in LiyubetBot

The Campaigns → Automations create/edit forms now offer **Image (optional)** inside each step.

- Upload PNG, JPG, WEBP or GIF (maximum 10 MB). Each step can be image + text caption, image only, or text only.
- Image captions are limited to 1,024 characters; text-only messages can use 4,000 characters. Existing URL buttons work with images.
- Images are stored on the configured default filesystem disk (S3 on Laravel Cloud is supported) under `media/automations` and registered through `media_files`. The automation step's existing nullable `media_file_id` column is reused; no migration is required.
- On editing, the original image is previewed; replacing it creates a new media entry, leaving previously used assets intact. Clearing the file removes the image for future sends.
- Existing automations with active participants may change the content (image, text and buttons) of current steps. Step count and delays remain locked while participants are active. Already sent messages are not modified.
- The automation background job already sends a photo (or animation for GIF) and its caption through `BotMessageSender`; the cached Telegram file ID is reused to reduce repeat file uploads.

## Rollout / verification

1. Deploy the new main-branch commit to the Laravel Cloud application. Ensure the configured filesystem disk is writable (and S3 credentials are configured when using S3).
2. Run `php artisan optimize:clear` and `php artisan queue:restart`.
3. In **Campaigns → Automations**, open an automation or create a test one. In a step, choose **Image (optional)** and add an optional caption.
4. Save, reopen and confirm the image preview persists. Test with an eligible Telegram account and a due automation, ensuring the `automation` queue worker and scheduler are running.
5. This change requires **no database migration**.
