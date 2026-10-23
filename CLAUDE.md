# CLAUDE.md

Online registration portal for the Nuhu Dolorosa charity run (PHP 8.0+, Stripe, PHPMailer). Live at https://nuhudolorosa.org on Hostinger (SSH alias `nuhu`, site in `~/domains/nuhudolorosa.org/public_html`, PHP 8.5.11).

## Work log (required, local only)

`work-log/` is a local record for the owner. It is listed in `.gitignore` and must never be committed or uploaded to the server (exclude it from any deploy/upload).

1. **After every completed task** (and deployed, when it deploys), save `work-log/YYYY-MM-DD-NN-short-title.md` (NN = running number for that day) with:
   - date, status (e.g. "live"), and commit hash(es)
   - what was asked for
   - what was done, in plain language for a non-developer
   - how it was checked (tests, live checks)
   - anything left for the owner to do, or open decisions

   Then add a row at the top of the "Completed work" table in `work-log/README.md` (Date | Task link | Commit(s)).

2. **After answering any question** (an explanation, with or without a change), save `work-log/answers/YYYY-MM-DD-NN-topic.md` with the question as the title, the date asked, and the full answer. If it led to a decision or change, link the related task file. Then add a row at the top of the "Answers to questions" table (Date | Question link).

Mention the saved file in the reply. If the owner says "save this", save the current reply immediately.
