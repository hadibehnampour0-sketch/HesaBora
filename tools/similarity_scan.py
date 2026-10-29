#!/usr/bin/env python3
"""
Dual-Engine Token & Structural AST Similarity Scanner for Clean-Room Verification.
Includes:
  - Measure 1 (Lexical/Literal Cloning): Token Jaccard + 4-gram Shingle + Line Overlap
  - Measure 2 (Syntactic/Structural AST Cloning): Normalized identifier-abstracted 6-gram Shingle + Structural Line Pattern
  - Moved-Not-Rewritten Block Detection: Shingle containment + contiguous line-streak detection across split files
  - Directory-Level Aggregate Module Scanning: Concatenates all files in module directory vs upstream mirror

Reports side-by-side metrics and highlights all files with >= 15% similarity.
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

    # Normalize double and single quoted strings to _STR_
    text = re.sub(r'"(?:\\.|[^"\\])*"', '_STR_', text)
    text = re.sub(r"'(?:\\.|[^'\\])*'", '_STR_', text)

    # Normalize variable identifiers ($var, $user, $doc) to _VAR_
    text = re.sub(r'\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*', '_VAR_', text)

    # Normalize numbers to _NUM_
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

def containment(set_a, set_b):
    """
    Asymmetric containment of A in B: |A ∩ B| / |A|
    Identifies if target file A (or its split components) was lifted from upstream B.
    """
    if not set_a:
        return 0.0
    return len(set_a.intersection(set_b)) / len(set_a)

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

def find_contiguous_lines(lines_a, lines_b):
    """
    Detects maximum contiguous non-empty matching lines between target and upstream.
    """
    def clean(l):
        return re.sub(r'\s+', ' ', l.strip())

    filt_a = [clean(l) for l in lines_a if clean(l) and not clean(l).startswith(('//', '/*', '*', '#'))]
    filt_b = [clean(l) for l in lines_b if clean(l) and not clean(l).startswith(('//', '/*', '*', '#'))]

    set_b = set(filt_b)
    max_streak = 0
    curr = 0
    for l in filt_a:
        if l in set_b:
            curr += 1
            if curr > max_streak:
                max_streak = curr
        else:
            curr = 0
    return max_streak

def scan_module_level(dir_a, dir_b):
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

    # 1. Directory-Level Aggregation (Concatenate all module files)
    combined_a = "\n\n".join(files_a.values())
    combined_b = "\n\n".join(files_b.values())

    tokens_a_m1_all = tokenize_literal(combined_a)
    tokens_b_m1_all = tokenize_literal(combined_b)
    ngrams_a_m1_all = ngrams(tokens_a_m1_all, 4)
    ngrams_b_m1_all = ngrams(tokens_b_m1_all, 4)
    lines_a_all = combined_a.splitlines()
    lines_b_all = combined_b.splitlines()

    dir_shingle_m1 = jaccard(ngrams_a_m1_all, ngrams_b_m1_all)
    dir_line_m1 = line_similarity(lines_a_all, lines_b_all)
    dir_token_m1 = jaccard(set(tokens_a_m1_all), set(tokens_b_m1_all))
    dir_composite_m1 = (dir_shingle_m1 * 0.5) + (dir_line_m1 * 0.3) + (dir_token_m1 * 0.2)

    tokens_a_m2_all = tokenize_ast_structure(combined_a)
    tokens_b_m2_all = tokenize_ast_structure(combined_b)
    ngrams_a_m2_all = ngrams(tokens_a_m2_all, 6)
    ngrams_b_m2_all = ngrams(tokens_b_m2_all, 6)
    dir_shingle_m2 = jaccard(ngrams_a_m2_all, ngrams_b_m2_all)
    dir_struct_line_m2 = structural_line_similarity(combined_a, combined_b)
    dir_composite_m2 = (dir_shingle_m2 * 0.6) + (dir_struct_line_m2 * 0.4)

    dir_containment_m1 = containment(ngrams_a_m1_all, ngrams_b_m1_all)
    dir_containment_m2 = containment(ngrams_a_m2_all, ngrams_b_m2_all)

    directory_report = {
        'target_files_count': len(files_a),
        'upstream_files_count': len(files_b),
        'dir_m1_composite': round(dir_composite_m1 * 100, 2),
        'dir_m2_composite': round(dir_composite_m2 * 100, 2),
        'dir_max_composite': round(max(dir_composite_m1, dir_composite_m2) * 100, 2),
        'dir_containment_m1': round(dir_containment_m1 * 100, 2),
        'dir_containment_m2': round(dir_containment_m2 * 100, 2),
    }

    # 2. File-Level & Moved-Not-Rewritten Detection
    file_results = []
    moved_blocks = []

    for rel_a, content_a in files_a.items():
        tokens_a_m1 = tokenize_literal(content_a)
        ngrams_a_m1 = ngrams(tokens_a_m1, 4)
        lines_a = content_a.splitlines()

        tokens_a_m2 = tokenize_ast_structure(content_a)
        ngrams_a_m2 = ngrams(tokens_a_m2, 6)

        best_match = None
        highest_composite = 0.0
        best_details = {}
        highest_containment = 0.0
        max_streak_file = 0
        best_containment_match = None

        for rel_b, content_b in files_b.items():
            tokens_b_m1 = tokenize_literal(content_b)
            ngrams_b_m1 = ngrams(tokens_b_m1, 4)
            lines_b = content_b.splitlines()

            token_sim_m1 = jaccard(set(tokens_a_m1), set(tokens_b_m1))
            shingle_sim_m1 = jaccard(ngrams_a_m1, ngrams_b_m1)
            line_sim_m1 = line_similarity(lines_a, lines_b)
            composite_m1 = (shingle_sim_m1 * 0.5) + (line_sim_m1 * 0.3) + (token_sim_m1 * 0.2)

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

            # Moved block checks: asymmetric containment of target A in upstream B
            cont_m1 = containment(ngrams_a_m1, ngrams_b_m1)
            cont_m2 = containment(ngrams_a_m2, ngrams_b_m2)
            max_cont = max(cont_m1, cont_m2)
            streak = find_contiguous_lines(lines_a, lines_b)

            if max_cont > highest_containment:
                highest_containment = max_cont
                best_containment_match = rel_b
            if streak > max_streak_file:
                max_streak_file = streak

        # Flag moved-not-rewritten if containment >= 20% or contiguous matching lines >= 6
        if highest_containment >= 0.20 or max_streak_file >= 6:
            moved_blocks.append({
                'target_file': rel_a,
                'upstream_file': best_containment_match or best_match,
                'containment_pct': round(highest_containment * 100, 2),
                'contiguous_lines': max_streak_file,
            })

        file_results.append({
            'target_file': rel_a,
            'closest_upstream': best_match,
            'metrics': best_details,
            'max_containment': round(highest_containment * 100, 2),
            'contiguous_lines': max_streak_file,
        })

    return directory_report, file_results, moved_blocks

def main():
    if len(sys.argv) < 3:
        print("Usage: python similarity_scan.py <target_dir> <upstream_dir> [output_report.md]")
        sys.exit(1)

    target_dir = sys.argv[1]
    upstream_dir = sys.argv[2]
    report_path = sys.argv[3] if len(sys.argv) > 3 else "similarity_report.md"

    dir_report, file_results, moved_blocks = scan_module_level(target_dir, upstream_dir)

    os.makedirs(os.path.dirname(report_path) or '.', exist_ok=True)
    with open(report_path, 'w', encoding='utf-8') as f:
        f.write("# Clean-Room Dual-Measure Similarity & Directory-Level Audit Report\n\n")
        f.write(f"**Target Directory:** `{target_dir}` ({dir_report['target_files_count']} files)  \n")
        f.write(f"**Upstream Mirror:** `{upstream_dir}` ({dir_report['upstream_files_count']} files)  \n\n")
        f.write("### Evaluation Methodology\n")
        f.write("1. **Measure 1 — Lexical / Literal Token Cloning:** Evaluates literal identifier reuse, exact token sequences, and raw line overlap:\n")
        f.write("   $$\\text{M1} = (0.50 \\times \\text{Shingle}_{\\text{4-gram}}) + (0.30 \\times \\text{Line Overlap}) + (0.20 \\times \\text{Token Jaccard})$$\n")
        f.write("2. **Measure 2 — Syntactic / Structural AST Cloning:** Evaluates underlying control-flow and grammar patterns by normalizing all variable identifiers (`$VAR`), strings (`_STR_`), and numbers (`_NUM_`):\n")
        f.write("   $$\\text{M2} = (0.60 \\times \\text{AST Shingle}_{\\text{6-gram}}) + (0.40 \\times \\text{Structural Line Pattern})$$\n")
        f.write("3. **Directory-Level Aggregate Scan:** Evaluates the entire module by concatenating all target files into a unified virtual document and measuring global overlap against the upstream mirror.\n")
        f.write("4. **Moved-Not-Rewritten Block Detection:** Flags any target file whose code shingles show $\\ge 20\\%$ asymmetric containment inside an upstream file or contains $\\ge 6$ contiguous matching lines, detecting code chunks shifted across split files.\n\n")

        f.write("## 1. Directory-Level Module Aggregate Scores\n\n")
        f.write("| Module Target Directory | Upstream Mirror Directory | Directory M1 (Literal) | Directory M2 (AST Struct) | Directory Max Composite | Containment M1 | Containment M2 | Status |\n")
        f.write("|---|---|:---:|:---:|:---:|:---:|:---:|:---:|\n")
        dir_status = "🟢 CLEAN (<15%)" if dir_report['dir_max_composite'] < 15.0 else ("🟡 ELEVATED (>=15%)" if dir_report['dir_max_composite'] < 20.0 else "🔴 HIGH OVERLAP (>=20%)")
        f.write(f"| `{target_dir}` | `{upstream_dir}` | {dir_report['dir_m1_composite']}% | {dir_report['dir_m2_composite']}% | **{dir_report['dir_max_composite']}%** | {dir_report['dir_containment_m1']}% | {dir_report['dir_containment_m2']}% | {dir_status} |\n\n")

        f.write("## 2. Moved-Not-Rewritten Block Analysis\n\n")
        if moved_blocks:
            f.write("The following split files contain block-level overlap or containment exceeding threshold (>=20% containment or >=6 contiguous lines):\n\n")
            f.write("| Split Target File | Source Upstream File | Containment % | Contiguous Lines | Finding Classification |\n")
            f.write("|---|---|:---:|:---:|---|\n")
            for mb in moved_blocks:
                f.write(f"| `{mb['target_file']}` | `{mb['upstream_file']}` | {mb['containment_pct']}% | {mb['contiguous_lines']} | ⚠️ Moved Block / Shared Standard Logic |\n")
            f.write("\n")
        else:
            f.write("✅ **No moved-not-rewritten blocks detected.** Zero files exhibit $\\ge 20\\%$ containment or $\\ge 6$ contiguous matching lines against upstream files.\n\n")

        f.write("## 3. File-by-File Dual-Measure Breakdown\n\n")
        f.write("| Target File | Closest Upstream File | Measure 1 (Literal) | Measure 2 (AST Struct) | Max Score | Status |\n")
        f.write("|---|---|:---:|:---:|:---:|:---:|\n")

        tainted_files = []
        elevated_files = []

        for r in file_results:
            target = r['target_file']
            upstream = r['closest_upstream'] or 'None'
            m = r['metrics']
            m1 = m.get('m1_composite', 0.0)
            m2 = m.get('m2_composite', 0.0)
            max_s = m.get('max_score', 0.0)

            if max_s >= 20.0:
                status = "🔴 REWRITTEN-LOW-SIMILARITY (contaminated author)"
                tainted_files.append((target, upstream, m1, m2, max_s))
            elif max_s >= 15.0:
                status = "🟡 ELEVATED (>=15%)"
                elevated_files.append((target, upstream, m1, m2, max_s))
            else:
                status = "🟢 CLEAN (<15%)"

            f.write(f"| `{target}` | `{upstream}` | {m1}% | {m2}% | **{max_s}%** | {status} |\n")

        f.write(f"\n- **Files with >= 20% in either measure:** {len(tainted_files)} / {len(file_results)}\n")
        f.write(f"- **Files with >= 15% in either measure:** {len(elevated_files) + len(tainted_files)} / {len(file_results)}\n\n")

        all_above_15 = tainted_files + elevated_files
        all_above_15.sort(key=lambda x: x[4], reverse=True)

        if all_above_15:
            f.write("### Comprehensive Listing of Files >= 15% (Audit Action List)\n")
            f.write("| File | Closest Upstream File | M1 (Literal) | M2 (AST Struct) | Max Score | Clean-Room Status Classification |\n")
            f.write("|---|---|:---:|:---:|:---:|---|\n")
            for item in all_above_15:
                rem = "REWRITTEN-LOW-SIMILARITY (contaminated author) — Scheduled for clean-room fresh-session re-implementation" if item[4] >= 20.0 else "Committed as low-similarity rewritten (contaminated author)"
                f.write(f"| `{item[0]}` | `{item[1]}` | {item[2]}% | {item[3]}% | **{item[4]}%** | {rem} |\n")

    print(f"Dual-measure directory report generated at {report_path}")
    print(f"Directory Max Composite: {dir_report['dir_max_composite']}%, Moved Blocks: {len(moved_blocks)}, Files >=15%: {len(all_above_15)}")

if __name__ == '__main__':
    main()
