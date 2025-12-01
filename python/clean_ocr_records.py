# Cleans ocr_records_cleaned.txt by skipping the first 3 lines and lines 30-34 (1-based), applies whitespace normalization, removes empty lines, and writes to ocr_records_cleaned_final.txt

def clean_ocr_records(input_path, output_path):
    with open(input_path, 'r', encoding='utf-8') as infile:
        lines = infile.readlines()
    # Known header/footer patterns to skip
    header_exact = {
        'ITEM', 'DESCRIPTION', 'PRICE', 'JOBBER PICK UP', 'MISC',
        'CURRENT BID LISTING BY CLASS FOR 75 JOBBER PICK UP',
        '9:20 AM', 'DATE', 'PAGE', 'BRAND', 'PACK', 'SIZE', 'UNIT', 'AMOUNT', 'TOTAL', 'EXT', 'QTY'
    }
    def is_header_or_footer(line):
        l = line.strip().upper()
        if not l:
            return True
        # Only skip if the whole line matches a known header/footer
        if l in (h.upper() for h in header_exact):
            return True
        # Skip lines that look like dates or page numbers
        if l.count('/') == 2 and len(l) <= 10:
            return True
        if l.startswith('FROM ') or l.startswith('TO '):
            return True
        if l.startswith('PG:') or l.startswith('PAGE'):
            return True
        return False
    cleaned = []
    for idx, line in enumerate(lines):
        # Remove first 3 lines and lines 30-34 (1-based, so 0-2 and 29-33)
        if idx < 3 or (29 <= idx <= 33):
            continue
        line_clean = line.strip()
        if is_header_or_footer(line_clean):
            continue
        if line_clean:
            cleaned.append(line_clean)
    with open(output_path, 'w', encoding='utf-8') as outfile:
        for line in cleaned:
            outfile.write(line + '\n')

if __name__ == '__main__':
    clean_ocr_records('../data/ocr_records_cleaned.txt', '../data/ocr_records_cleaned_final.txt')
