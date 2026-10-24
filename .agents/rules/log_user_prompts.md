---
description: Mandatory rule to log all user prompts in USER_PROMPTS.txt
alwaysApply: true
---

# User Prompts Recording Rule

Whenever the user provides any prompt or instruction in this project (`alfgb`), you MUST keep the record updated in `USER_PROMPTS.txt` at the root of the project:

1. **Target File**: `c:\xampp\htdocs\alfgb\USER_PROMPTS.txt` (opens directly with Windows Notepad).
2. **Action on Each Turn**:
   - Check if the incoming user prompt is already recorded in `USER_PROMPTS.txt`.
   - If not yet recorded, append it as the next sequential prompt (`PROMPT #XXX`) with:
     - Date & Time (Local and UTC)
     - Session / Conversation ID
     - Exact verbatim prompt text
   - Update `Total Prompts Logged`, `Latest Prompt Date`, and `File Last Updated` in the file header.
   - Maintain Windows CRLF (`\r\n`) line endings.
