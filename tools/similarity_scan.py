#!/usr/bin/env python3
"""
Dual-Engine Token & Structural AST Similarity Scanner for Clean-Room Verification.
Compares a target directory against an upstream/tainted mirror and outputs:
  - Measure 1 (Lexical/Literal Cloning): Token Jaccard + 4-gram Shingle + Line Overlap
  - Measure 2 (Syntactic/Structural AST Cloning): Normalized identifier-abstracted 6-gram Shingle + Structural Line Pattern

Reports both measures side-by-side and highlights all files with >= 15% similarity.
"""
import os
import sys
import re

def tokenize_literal(text):
    text = re.sub(r'/\*.*?\*/', ' ', text, flags=re.DOTALL)
    text = re.sub(r'//.*', ' ', text)
    text = re.sub(r'#.*', ' ', text)
    tokens = re.findall(r'[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*|[0-9]+|[^\s\w]', text)
    return tokens

def tokenize_ast_structure(text):
    """
    Measure 2: Normalized Structural AST Tokenizer.
    Abstracts variable names, string literals, and numbers to evaluate
    syntactic flow and statement grammar independently of identifier naming.
    """
    text = re.sub(r'/\*.*?\*/', ' ', text, flags=re.DOTALL)
    text = re.sub(r'//.*', ' ', text)
    text = re.sub(r'#.*', ' ', text)

    # Normalize double and single quoted strings to $STR
    text = re.sub(r'"(?:\\.|[^"\\])*"', '_STR_', text)
    text = re.sub(r"'(?:\\.|[^'\\])*'", '_STR_', text)

    # Normalize variable identifiers ($var, $user, $doc) to $VAR
    text = re.sub(r'\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*', '_VAR_', text)

    # Normalize numbers to $NUM
    text = re.sub(r'\b[0-9]+(?:\.[0-9]+)?\b', '_NUM_', text)

    tokens = re.findall(r'[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*|[^\s\w]', text)
    return tokens

def ngrams(tokens, n=6):
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

def structural_line_similarity(content_a, content_b):
    def normalize_line(l):
        l = l.strip()
        if not l or l.startswith(('//', '/*', '*', '#')):
            return ''
        l = re.sub(r'"(?:\\.|[^"\\])*"', '_STR_', l)
        l = re.sub(r"'(?:\\.|[^'\\])*'", '_STR_', l)
        l = re.sub(r'\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*', '_VAR_', l)
        l = re.sub(r'\b[0-9]+\b', '_NUM_', l)
        return re.sub(r'\s+', ' ', l)

    lines_a = set(normalize_line(l) for l in content_a.splitlines() if normalize_line(l))
    lines_b = set(normalize_line(l) for l in content_b.splitlines() if normalize_line(l))
    return jaccard(lines_a, lines_b)

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
        tokens_a_m1 = tokenize_literal(content_a)
        ngrams_a_m1 = ngrams(tokens_a_m1, 4)
        lines_a = content_a.splitlines()

        tokens_a_m2 = tokenize_ast_structure(content_a)
        ngrams_a_m2 = ngrams(tokens_a_m2, 6)

        best_match = None
        highest_composite = 0.0
        best_details = {}

        for rel_b, content_b in files_b.items():
            # Measure 1: Literal Token Jaccard (20%) + 4-gram Shingle (50%) + Line (30%)
            tokens_b_m1 = tokenize_literal(content_b)
            ngrams_b_m1 = ngrams(tokens_b_m1, 4)
            lines_b = content_b.splitlines()

            token_sim_m1 = jaccard(set(tokens_a_m1), set(tokens_b_m1))
            shingle_sim_m1 = jaccard(ngrams_a_m1, ngrams_b_m1)
            line_sim_m1 = line_similarity(lines_a, lines_b)
            composite_m1 = (shingle_sim_m1 * 0.5) + (line_sim_m1 * 0.3) + (token_sim_m1 * 0.2)

            # Measure 2: Normalized AST 6-gram Shingle (60%) + Structural Line Pattern (40%)
            tokens_b_m2 = tokenize_ast_structure(content_b)
            ngrams_b_m2 = ngrams(tokens_b_m2, 6)
            shingle_sim_m2 = jaccard(ngrams_a_m2, ngrams_b_m2)
            struct_line_sim = structural_line_similarity(content_a, content_b)
            composite_m2 = (shingle_sim_m2 * 0.6) + (struct_line_sim * 0.4)

            overall_max = max(composite_m1, composite_m2)
            if overall_max > highest_composite:
                highest_composite = overall_max
                best_match = rel_b
                best_details = {
                    'm1_composite': round(composite_m1 * 100, 2),
                    'm1_shingle': round(shingle_sim_m1 * 100, 2),
                    'm1_line': round(line_sim_m1 * 100, 2),
                    'm1_token': round(token_sim_m1 * 100, 2),
                    'm2_composite': round(composite_m2 * 100, 2),
                    'm2_ast_shingle': round(shingle_sim_m2 * 100, 2),
                    'm2_struct_line': round(struct_line_sim * 100, 2),
                    'max_score': round(overall_max * 100, 2),
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
        f.write("# Clean-Room Dual-Measure Similarity Audit Report\n\n")
        f.write(f"**Target Directory:** `{target_dir}`  \n")
        f.write(f"**Upstream Mirror:** `{upstream_dir}`  \n\n")
        f.write("### Evaluation Methodology\n")
        f.write("1. **Measure 1 — Lexical / Literal Token Cloning:** Evaluates literal identifier reuse, exact token sequences, and raw line overlap:\n")
        f.write("   $$\\text{M1} = (0.50 \\times \\text{Shingle}_{\\text{4-gram}}) + (0.30 \\times \\text{Line Overlap}) + (0.20 \\times \\text{Token Jaccard})$$\n")
        f.write("2. **Measure 2 — Syntactic / Structural AST Cloning:** Evaluates underlying control-flow and grammar patterns by normalizing all variable identifiers (`$VAR`), strings (`_STR_`), and numbers (`_NUM_`):\n")
        f.write("   $$\\text{M2} = (0.60 \\times \\text{AST Shingle}_{\\text{6-gram}}) + (0.40 \\times \\text{Structural Line Pattern})$$\n\n")
        f.write("| Target File | Closest Upstream File | Measure 1 (Literal) | Measure 2 (AST Struct) | Max Score | Status |\n")
        f.write("|---|---|:---:|:---:|:---:|:---:|\n")

        tainted_files = []
        elevated_files = []

        for r in results:
            target = r['target_file']
            upstream = r['closest_upstream'] or 'None'
            m = r['metrics']
            m1 = m.get('m1_composite', 0.0)
            m2 = m.get('m2_composite', 0.0)
            max_s = m.get('max_score', 0.0)

            if max_s >= 20.0:
                status = "🔴 TAINTED (>=20%)"
                tainted_files.append((target, upstream, m1, m2, max_s))
            elif max_s >= 15.0:
                status = "🟡 ELEVATED (>=15%)"
                elevated_files.append((target, upstream, m1, m2, max_s))
            else:
                status = "🟢 CLEAN (<15%)"

            f.write(f"| `{target}` | `{upstream}` | {m1}% | {m2}% | **{max_s}%** | {status} |\n")

        f.write(f"\n- **Files with >= 20% in either measure:** {len(tainted_files)} / {len(results)}\n")
        f.write(f"- **Files with >= 15% in either measure:** {len(elevated_files) + len(tainted_files)} / {len(results)}\n\n")

        all_above_15 = tainted_files + elevated_files
        all_above_15.sort(key=lambda x: x[4], reverse=True)

        if all_above_15:
            f.write("### Comprehensive Listing of Files >= 15% (Audit Action List)\n")
            f.write("In accordance with the clean-room governance protocol, all files with $\\ge 15\\%$ in either measure are listed below with their specific metric breakdown:\n\n")
            f.write("| File | Closest Upstream File | M1 (Literal) | M2 (AST Struct) | Max Score | Remediation Status |\n")
            f.write("|---|---|:---:|:---:|:---:|---|\n")
            for item in all_above_15:
                rem = "Pending true clean-room isolated session re-implementation" if item[4] >= 20.0 else "Committed as low-similarity rewritten; flagged for future refactoring"
                f.write(f"| `{item[0]}` | `{item[1]}` | {item[2]}% | {item[3]}% | **{item[4]}%** | {rem} |\n")

    print(f"Dual-measure report generated at {report_path}")
    print(f"Total: {len(results)}, >=20%: {len(tainted_files)}, >=15%: {len(all_above_15)}")

if __name__ == '__main__':
    main()
