---
name: moodle-orb
description: Maintain and extend the MTPC Moodle Orb and its Gemini Live integration; use for Moodle Orb UI, audio, navigation, student tools, permissions, or deployment changes in this repository.
---

# MTPC Moodle Orb

Use this skill for changes that touch the Moodle student Orb, the admin Orb reference implementation, the Moodle bridge plugin, or the MTPC Moodle theme. The goal is to keep the student Orb behavior aligned with the admin Orb while preserving a strict student read-only boundary.

## Source of truth

- Admin reference: `admin/index.html`. Inspect its Gemini Live functions before changing equivalent Moodle behavior, especially `adminConnectLive`, `adminStartMic`, `adminPlayAudio`, `adminHandleLive`, and `adminSendToolResponses`.
- Student client: `moodle-theme/mtpc/javascript/mtpc-orb.js`.
- Student styles: `moodle-theme/mtpc/scss/mtpc.scss`.
- Authenticated student bridge: `moodle-plugin/local/mtpcbridge/moodle-orb.php`.
- Authenticated one-use Live token endpoint: `moodle-plugin/local/mtpcbridge/moodle-live-token.php`.
- Shared student Moodle tool implementation: `admin/api/orb-agent.php`, function `mtpc_orb_agent_moodle_student_tool`.
- Deployment: `.cpanel.yml` copies both the Moodle plugin and theme to the live Moodle installation.

## Non-negotiable behavior

1. The Moodle Orb is for the currently authenticated Moodle student only. Every course request must be checked against the courses returned by the current Moodle session. Never trust a course ID supplied by the browser or model without server validation.
2. The only Live tool exposed to Moodle is `moodle_student_action`. It is read-only and may cover the student's own courses, contents, assignments, quizzes, grades, completion, forums, announcements, calendar, and verified course navigation. Never expose `moodle_action`, admin tools, Zalo tools, user lists, enrolment, grading, create, update, delete, send, or other write operations.
3. Keep the Live engine aligned with admin: constrained Gemini Live WebSocket, model `gemini-3.1-flash-live-preview`, native PCM microphone input at 16 kHz, native PCM output at 24 kHz, input/output transcription, automatic activity detection, and the `Zephyr` voice unless the admin reference changes.
4. Do not reintroduce browser `SpeechRecognition` or `speechSynthesis` as a substitute for Gemini Live. If Live is unavailable, text input may remain available, but do not claim that voice is working.
5. Course navigation is a verified read-only action. Return a Moodle `course/view.php?id=...` URL only from the server after enrollment validation, then let the client navigate. Do not allow arbitrary model-provided URLs.
6. Keep the Orb-first visual experience consistent with the admin Orb: one conversation workspace, transcript, composer, microphone/audio state, responsive layout, and no redundant suggestion-chip area. Preserve keyboard focus, Escape-to-close, reduced-motion support, and mobile layout.

## Change workflow

- Read the relevant admin implementation and current Moodle implementation before editing; do not recreate the Live protocol from memory.
- For a new student capability, add the server-side authorization/validation first, then add the narrow Live declaration and client handling. A prompt instruction alone is not a permission boundary.
- Use `apply_patch` for source edits. Preserve unrelated working-tree changes, especially `README.md` and `SETUP.md` unless the user explicitly asks for them.
- Bump the appropriate Moodle plugin/theme version when deployed PHP, JavaScript, or SCSS changes. Keep `.cpanel.yml` deployment paths working.
- Run at least:
  - `node --check moodle-theme/mtpc/javascript/mtpc-orb.js`
  - `php -l moodle-plugin/local/mtpcbridge/moodle-orb.php`
  - `php -l moodle-plugin/local/mtpcbridge/moodle-live-token.php`
  - `node tests/moodle-theme-contract-test.cjs`
  - `node tests/moodle-tool-contract-test.cjs`
  - `git diff --check`
- Before reporting completion, state the commit hash and remind the user to update/deploy from cPanel, visit Moodle Notifications, purge caches, and hard-refresh the browser.

## Safety and diagnosis

- A message such as “đã mở khóa học” is not evidence of navigation. Verify that the tool result contains a server-generated redirect URL and that the client calls `window.location.assign`.
- A visible Orb with no response requires checking the authenticated token endpoint, WebSocket setup, microphone permission, and browser console separately. A favicon 404 is unrelated to Gemini Live.
- Keep API keys server-side. The browser may receive only the constrained one-use Live token; never put `GEMINI_API_KEY` in theme JavaScript.
- Do not deploy untested changes directly to the live site or broaden student permissions to make a tool request appear to work.
