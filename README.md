# How to request an app certificate

Read and follow the [App Store documentation on obtaining a certificate](https://nextcloudappstore.readthedocs.io/en/latest/developer.html#obtaining-a-certificate). It is the reference for the whole process.

When you open the pull request:

- Keep the whole [PR template](.github/pull_request_template.md). Fill it in and tick the boxes that apply, without removing anything.
- Sign off every commit yourself, as described below.
- Make sure all automated checks pass, including the DCO check. No certificate is generated until they do.

You don't need to mention anybody. People who can help here are subscribed to this repo and will get back to you.

## Developer Certificate of Origin (DCO)

Every commit must carry a `Signed-off-by: Real Name <email>` line matching the commit author, certifying the [Developer Certificate of Origin](https://github.com/nextcloud/server/blob/master/contribute/developer-certificate-of-origin). Only a human can add it, never an AI tool or bot. Use `git commit -s`, or add the line at the end of the commit description in the GitHub web interface.

## AI tools

If you use AI, follow the [Nextcloud AI Contribution Policy](https://github.com/nextcloud/.github/blob/master/AI_POLICY.md). Coding assistants must follow [AGENTS.md](AGENTS.md).
