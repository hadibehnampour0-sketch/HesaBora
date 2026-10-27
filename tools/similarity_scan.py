#!/usr/bin/env python3
"""
Token-clone and structural similarity scanner for clean-room verification.
Compares a target directory against an upstream/tainted mirror and outputs
detailed similarity metrics (Token Jaccard, N-gram shingle, line overlap).
"""
import os
import sys
import re
import json

def tokenize(text):
    # Strip comments and docblocks
    text = re.sub(r'/\*.*?\*/', ' ', text, flags=re.DOTALL)
    text = re.sub(r'//.*', ' ', text)
    text = re.sub(r'#.*', ' ', text)
    # Extract identifiers, numbers, operators, keywords
    tokens = re.findall(r'[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*|[0-9]+|[^\s\w]', text)
    return tokens

def ngrams(tokens, n=4):
    if len(tokens) < n:
        return set([tuple(tokens)])
    return set(tuple(tokens[i:i+n]) for i in range(len(tokens) - n + 1))

def jaccard(set_a, set_b):
    if not set_a and not set_b:
        return 0.0
    intersection = len(set_a.intersection(set_b))
    union = len(set_a.union(set_b))
    return intersection / union if union > 0 else 0.0

def line_similarity(lines_a, lines_b):
    set_a = set(l.strip() for l in lines_a if l.strip() and not l.strip().startswith(('//', '/*', '*', '#')))
    set_b = set(l.strip() for l in lines_b if l.strip() and not l.strip().startswith(('//', '/*', '*', '#')))
    return jaccard(set_a, set_b)

def scan_directories(dir_a, dir_b):
    files_a = {}
    for root, _, files in os.walk(dir_a):
        for f in files:
            if f.endswith(('.php', '.inc')):
                rel = os.path.relpath(os.path.join(root, f), dir_a)
                with open(os.path.join(root, f), encoding='utf-8', errors='ignore') as fp:
                    files_a[rel] = fp.read()

    files_b = {}
    for root, _, files in os.walk(dir_b):
        for f in files:
            if f.endswith(('.php', '.inc')):
                rel = os.path.relpath(os.path.join(root, f), dir_b)
                with open(os.path.join(root, f), encoding='utf-8', errors='ignore') as fp:
                    files_b[rel] = fp.read()

    results = []
    for rel_a, content_a in files_a.items():
        tokens_a = tokenize(content_a)
        ngrams_a = ngrams(tokens_a, 4)
        lines_a = content_a.splitlines()

        best_match = None
        highest_jaccard = 0.0
        best_details = {}

        for rel_b, content_b in files_b.items():
            tokens_b = tokenize(content_b)
            ngrams_b = ngrams(tokens_b, 4)
            lines_b = content_b.splitlines()

            token_sim = jaccard(set(tokens_a), set(tokens_b))
            shingle_sim = jaccard(ngrams_a, ngrams_b)
            line_sim = line_similarity(lines_a, lines_b)

            composite = (shingle_sim * 0.5) + (line_sim * 0.3) + (token_sim * 0.2)
            if composite > highest_jaccard:
                highest_jaccard = composite
                best_match = rel_b
                best_details = {
                    'token_sim': round(token_sim * 100, 2),
                    'shingle_4gram_sim': round(shingle_sim * 100, 2),
                    'line_sim': round(line_sim * 100, 2),
                    'composite_score': round(composite * 100, 2),
                }

        results.append({
            'target_file': rel_a,
            'closest_upstream': best_match,
            'metrics': best_details
        })

    return results

def main():
    if len(sys.argv) < 3:
        print("Usage: python similarity_scan.py <target_dir> <upstream_dir> [output_report.md]")
        sys.exit(1)

    target_dir = sys.argv[1]
    upstream_dir = sys.argv[2]
    report_path = sys.argv[3] if len(sys.argv) > 3 else "similarity_report.md"

    results = scan_directories(target_dir, upstream_dir)

    os.makedirs(os.path.dirname(report_path) or '.', exist_ok=True)
    with open(report_path, 'w', encoding='utf-8') as f:
        f.write("# Clean-Room Similarity Audit Report\n\n")
        f.write(f"**Target Directory:** `{target_dir}`  \n")
        f.write(f"**Upstream Mirror:** `{upstream_dir}`  \n")
        f.write(f"**Scan Method:** Token Jaccard + 4-gram Shingle Cloning + Semantic Line Overlap\n\n")
        f.write("| Target File | Closest Upstream File | 4-Gram Shingle Sim | Line Overlap | Token Sim | Composite Score | Result |\n")
        f.write("|---|---|---|---|---|---|---|\n")

        high_risk_count = 0
        for r in results:
            target = r['target_file']
            upstream = r['closest_upstream'] or 'None'
            m = r['metrics']
            comp = m.get('composite_score', 0.0)
            status = "🔴 TAINTED / HIGH" if comp > 40 else ("🟡 MEDIUM" if comp > 20 else "🟢 CLEAN (<20%)")
            if comp > 40:
                high_risk_count += 1
            f.write(f"| `{target}` | `{upstream}` | {m.get('shingle_4gram_sim', 0)}% | {m.get('line_sim', 0)}% | {m.get('token_sim', 0)}% | **{comp}%** | {status} |\n")

        f.write(f"\n**High Risk / Tainted Files (>40% similarity):** {high_risk_count} / {len(results)}\n")

    print(f"Report written to {report_path}")

if __name__ == '__main__':
    main()
