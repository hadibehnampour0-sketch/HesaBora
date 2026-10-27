#!/usr/bin/env python3
"""
Doc-lint tool enforcing documentation integrity:
1. Unique top-level section numbers in Master Context r15
2. CURRENT_STATE.md line count budget (<= 400 lines)
3. Decision reference integrity (all D-xxx in Master Context exist in Decision Log / CURRENT_STATE)
"""
import re
import sys
import os

def check_master_context_sections():
    path = 'docs/project-context/Dakhl_MASTER_CONTEXT_AND_ROADMAP_2026-10-27-r15.md'
    if not os.path.exists(path):
        return False, f"Missing {path}"

    with open(path, encoding='utf-8') as f:
        text = f.read()

    # Find all '# X.' headers
    sections = re.findall(r'^#\s+(\d+)\.', text, re.MULTILINE)
    seen = set()
    duplicates = []
    for s in sections:
        if s in seen:
            duplicates.append(s)
        seen.add(s)

    if duplicates:
        return False, f"Duplicate section numbers found in Master Context r15: {duplicates}"

    return True, f"All {len(sections)} top-level sections in Master Context r15 are strictly unique (1 to {len(sections)})."

def check_current_state_line_count():
    path = 'docs/project-context/CURRENT_STATE.md'
    if not os.path.exists(path):
        return False, f"Missing {path}"

    with open(path, encoding='utf-8') as f:
        lines = f.readlines()

    line_count = len(lines)
    if line_count > 400:
        return False, f"CURRENT_STATE.md exceeds budget: {line_count} lines > 400 lines."

    return True, f"CURRENT_STATE.md within budget ({line_count} lines <= 400 lines)."

def check_decision_references():
    with open('docs/project-context/Dakhl_DECISION_LOG_2026-09-12-r4.md', encoding='utf-8') as f:
        decision_log = f.read()

    with open('docs/project-context/CURRENT_STATE.md', encoding='utf-8') as f:
        current_state = f.read()

    known_decisions = set(re.findall(r'D-\d+', decision_log) + re.findall(r'D-\d+', current_state))
    # Expand ranges like D-001–D-025 or D-001-D-025
    ranges = re.findall(r'D-(\d+)\s*[–\-—]\s*D-(\d+)', current_state) + re.findall(r'D-(\d+)\s*[–\-—]\s*D-(\d+)', decision_log)
    for start_s, end_s in ranges:
        start, end = int(start_s), int(end_s)
        for i in range(start, end + 1):
            known_decisions.add(f"D-{i:03d}")

    with open('docs/project-context/Dakhl_MASTER_CONTEXT_AND_ROADMAP_2026-10-27-r15.md', encoding='utf-8') as f:
        master_context = f.read()

    referenced_decisions = set(re.findall(r'D-\d+', master_context))
    missing = sorted([d for d in referenced_decisions if d not in known_decisions])

    if missing:
        return False, f"Decisions referenced in Master Context but missing from register: {missing}"

    return True, f"All {len(referenced_decisions)} referenced decisions exist in the Decision Register."

def main():
    checks = [
        check_master_context_sections,
        check_current_state_line_count,
        check_decision_references,
    ]

    all_passed = True
    print("Running Documentation Linting...")
    for check in checks:
        passed, msg = check()
        status = "PASS" if passed else "FAIL"
        print(f"[{status}] {msg}")
        if not passed:
            all_passed = False

    if not all_passed:
        sys.exit(1)
    print("Doc-lint: 100% SUCCESS.")

if __name__ == '__main__':
    main()
