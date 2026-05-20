import os
import sys
import socket
import json
import re
import pdfplumber
import csv
import io
import argparse
from flask import Flask, request, jsonify
from typing import Dict, List, Optional, Tuple, Set, Any

app = Flask(__name__)

# Global flag to control debug output - disabled by default
DEBUG_ENABLED = False

def extract_data(pdf_path: str, year: Optional[str] = None) -> Dict:
    """
    Extract student data from KCSE-style PDFs.
    """
    
    def debug_log(message: str, data: Any = None) -> None:
        """Helper function for debug logging."""
        if DEBUG_ENABLED:
            if data is not None:
                print(f"DEBUG: {message} - {data}", file=sys.stderr)
            else:
                print(f"DEBUG: {message}", file=sys.stderr)
    
    # Extract all text with structure preservation
    all_lines = []
    
    with pdfplumber.open(pdf_path) as pdf:
        for page_num, page in enumerate(pdf.pages):
            # Extract tables
            tables = page.extract_tables()
            if tables:
                for table_idx, table in enumerate(tables):
                    for row_idx, row in enumerate(table):
                        cleaned_row = []
                        for cell in row:
                            if cell is not None and str(cell).strip():
                                cleaned_cell = re.sub(r'\s+', ' ', str(cell).strip())
                                cleaned_row.append(cleaned_cell)
                        if cleaned_row:
                            all_lines.append(cleaned_row)
            
            # Extract text
            text = page.extract_text()
            if text:
                lines = text.split('\n')
                for line_idx, line in enumerate(lines):
                    cleaned_line = re.sub(r'\s+', ' ', line.strip())
                    if cleaned_line and not any(x in cleaned_line.lower() for x in 
                                              ['kcse', 'ntid', 'page', 'header', 'footer']):
                        tokens = cleaned_line.split()
                        if tokens:
                            all_lines.append(tokens)
    
    # Flatten for pattern scanning
    all_tokens = []
    for line in all_lines:
        all_tokens.extend(line)
        all_tokens.append("|LINE_END|")
    
    # Look for student ID patterns
    student_id_patterns = [
        r'^S\d{3,}$',            # S001, S1234
        r'^\d{4}_S\d+',          # 2024_S001
        r'^[A-Z]{2,3}\d+$',      # AB123, XYZ456
        r'^\d+[A-Z]+\d*$',       # 123AB, 456XYZ
        r'^[A-Z]+\d+[A-Z]*$',    # ABC123, A1B2
    ]
    
    potential_student_ids = []
    for i, token in enumerate(all_tokens):
        for pattern in student_id_patterns:
            if re.match(pattern, token, re.IGNORECASE):
                potential_student_ids.append((i, token))
                break
    
    # Look for score patterns (clusters of numbers)
    score_clusters = []
    i = 0
    while i < len(all_tokens):
        if re.match(r'^\d{1,3}(\.\d{1,2})?$', all_tokens[i]) or all_tokens[i].lower() in ['nan', 'none']:
            cluster_start = i
            cluster_length = 0
            while i < len(all_tokens) and (re.match(r'^\d{1,3}(\.\d{1,2})?$', all_tokens[i]) or 
                                          all_tokens[i].lower() in ['nan', 'none']):
                cluster_length += 1
                i += 1
            if cluster_length >= 8:  # Reasonable minimum for a student record
                score_clusters.append((cluster_start, cluster_length))
        else:
            i += 1
    
    # Extract student records
    students = []
    used_indices = set()
    
    # Method 1: Start from student IDs
    for idx, student_id in potential_student_ids:
        if idx in used_indices:
            continue
            
        # Find the nearest score cluster to this student ID
        best_cluster = None
        best_distance = float('inf')
        
        for cluster_start, cluster_length in score_clusters:
            distance = abs(cluster_start - idx)
            if distance < best_distance and cluster_start not in used_indices:
                best_distance = distance
                best_cluster = (cluster_start, cluster_length)
        
        if best_cluster and best_distance < 20:  # Reasonable maximum distance
            cluster_start, cluster_length = best_cluster
            
            # Extract student info
            student_data = {
                'student_id': student_id,
                'name': extract_name(all_tokens, idx),
                'scores': extract_scores(all_tokens, cluster_start, cluster_length),
                'mean_points': '',
                'mean_grade': ''
            }
            
            # Try to find mean points and grade near the scores
            mean_info = extract_mean_info(all_tokens, cluster_start, cluster_length)
            student_data.update(mean_info)
            
            students.append(student_data)
            used_indices.add(idx)
            used_indices.add(cluster_start)
    
    # Method 2: Start from score clusters without clear student IDs
    for cluster_start, cluster_length in score_clusters:
        if cluster_start in used_indices:
            continue
            
        # Look backwards for a student ID
        student_id = None
        for i in range(cluster_start - 1, max(0, cluster_start - 15), -1):
            if i in used_indices:
                break
                
            for pattern in student_id_patterns:
                if re.match(pattern, all_tokens[i], re.IGNORECASE):
                    student_id = all_tokens[i]
                    break
            if student_id:
                break
        
        if student_id:
            student_data = {
                'student_id': student_id,
                'name': extract_name(all_tokens, cluster_start - 1),
                'scores': extract_scores(all_tokens, cluster_start, cluster_length),
                'mean_points': '',
                'mean_grade': ''
            }
            
            # Try to find mean points and grade near the scores
            mean_info = extract_mean_info(all_tokens, cluster_start, cluster_length)
            student_data.update(mean_info)
            
            students.append(student_data)
            used_indices.add(cluster_start)
    
    # Convert to final format
    all_data = []
    for student in students:
        row = {
            "name": f"{year}_{student['student_id']}_{student['name']}" 
                    if year else f"{student['student_id']}_{student['name']}",
            "english": student['scores'].get('english', ''),
            "math": student['scores'].get('math', ''),
            "kiswahili": student['scores'].get('kiswahili', ''),
            "cre": student['scores'].get('cre', ''),
            "chemistry": student['scores'].get('chemistry', ''),
            "physics": student['scores'].get('physics', ''),
            "biology": student['scores'].get('biology', ''),
            "geography": student['scores'].get('geography', ''),
            "history": student['scores'].get('history', ''),
            "business": student['scores'].get('business', ''),
            "agriculture": student['scores'].get('agriculture', ''),
            "computer": student['scores'].get('computer', ''),
            "mean_points": student.get('mean_points', ''),
            "mean_grade": student.get('mean_grade', '')
        }
        all_data.append(row)
    
    # Deduplicate by student_id
    seen_ids = set()
    unique_data = []
    
    for row in all_data:
        # Extract student ID from name field
        parts = row["name"].split("_")
        if len(parts) >= 2:
            sid = parts[-2] if len(parts) > 2 else parts[0]
        else:
            sid = row["name"]
            
        if sid not in seen_ids:
            seen_ids.add(sid)
            unique_data.append(row)
    
    # Generate CSV
    header = ["name", "english", "math", "kiswahili", "cre", "chemistry",
              "physics", "biology", "geography", "history", "business",
              "agriculture", "computer", "mean_points", "mean_grade"]
    
    output = io.StringIO()
    writer = csv.writer(output)
    writer.writerow(header)
    
    for row in unique_data:
        writer.writerow([row.get(col, "") for col in header])
    
    csv_text = output.getvalue()
    
    return {
        "success": True, 
        "csv": csv_text, 
        "count": len(unique_data), 
        "year": year
    }

def extract_name(tokens: List[str], start_idx: int) -> str:
    """Extract a student name from tokens starting at the given index."""
    name_parts = []
    
    # First check if the current token is already in Student_XXXX format
    current_token = tokens[start_idx] if start_idx < len(tokens) else ""
    if current_token.startswith("Student_") and current_token[8:].isdigit():
        return current_token
    
    # Look backwards first (name might be before the ID)
    for i in range(start_idx - 1, max(0, start_idx - 5), -1):
        token = tokens[i]
        if (re.match(r'^[A-Z][a-z]+$', token) and 
            token.lower() not in ['nan', 'none', 'null', 'student', 'name', 'id']):
            name_parts.insert(0, token)
        # Also check for Student_XXXX format
        elif token.startswith("Student_") and token[8:].isdigit():
            name_parts.insert(0, token)
    
    # Look forwards if we didn't find enough name parts
    if len(name_parts) < 2:
        for i in range(start_idx + 1, min(len(tokens), start_idx + 5)):
            token = tokens[i]
            if (re.match(r'^[A-Z][a-z]+$', token) and 
                token.lower() not in ['nan', 'none', 'null', 'student', 'name', 'id']):
                name_parts.append(token)
            # Also check for Student_XXXX format
            elif token.startswith("Student_") and token[8:].isdigit():
                name_parts.append(token)
            elif name_parts:  # Stop if we already have name parts
                break
    
    # If we found Student_XXXX format, return it directly
    for part in name_parts:
        if part.startswith("Student_") and part[8:].isdigit():
            return part
    
    return " ".join(name_parts) if name_parts else "Unknown"

def extract_scores(tokens: List[str], start_idx: int, length: int) -> Dict[str, str]:
    """Extract scores from a cluster of tokens."""
    subjects = [
        'english', 'math', 'kiswahili', 'cre',
        'chemistry', 'physics', 'biology',
        'geography', 'history', 'business',
        'agriculture', 'computer'
    ]
    
    scores = {}
    for i in range(min(len(subjects), length)):
        token_idx = start_idx + i
        if token_idx < len(tokens):
            token = tokens[token_idx]
            scores[subjects[i]] = '' if token.lower() in ['nan', 'none', 'null'] else token
        else:
            scores[subjects[i]] = ''
    
    return scores

def extract_mean_info(tokens: List[str], start_idx: int, length: int) -> Dict[str, str]:
    """Extract mean points and grade from around a score cluster."""
    result = {'mean_points': '', 'mean_grade': ''}
    
    # Look after the scores
    for i in range(start_idx + length, min(len(tokens), start_idx + length + 5)):
        token = tokens[i]
        if re.match(r'^\d{1,2}(\.\d{1,2})?$', token) and not result['mean_points']:
            result['mean_points'] = token
        elif re.match(r'^[A-E][+-]?$', token, re.IGNORECASE) and not result['mean_grade']:
            result['mean_grade'] = token
    
    # Look before the scores if not found after
    if not result['mean_points'] or not result['mean_grade']:
        for i in range(max(0, start_idx - 5), start_idx):
            token = tokens[i]
            if re.match(r'^\d{1,2}(\.\d{1,2})?$', token) and not result['mean_points']:
                result['mean_points'] = token
            elif re.match(r'^[A-E][+-]?$', token, re.IGNORECASE) and not result['mean_grade']:
                result['mean_grade'] = token
    
    return result

def parse_exam_pdf(pdf_path, class_name, stream, exam_name, term):
    """
    Parse school exam PDF results with class, stream, exam name, and term information.
    Enhanced version with multiple parsing strategies.
    """
    results = []
    
    # Define subject names in the EXACT order expected by PHP
    # Order must match: english, math, kiswahili, cre, chemistry, physics, 
    # biology, geography, history, business, agriculture, computer
    subject_names = [
        "english", "math", "kiswahili", "cre", "chemistry", "physics", 
        "biology", "geography", "history", "business", "agriculture", "computer"
    ]
    
    # Define the choice fields to look for
    science_choices = {"physics", "biology", "chemistry"}
    humanity_choices = {"geography", "history", "business"}
    technical_choices = {"agriculture", "computer"}
    all_choices = science_choices | humanity_choices | technical_choices
    
    # Debug: show extracted text
    if DEBUG_ENABLED:
        print("DEBUG: Extracting text from PDF...")
        with pdfplumber.open(pdf_path) as pdf:
            for i, page in enumerate(pdf.pages):
                text = page.extract_text()
                print(f"DEBUG: Page {i} text:\n{text}\n{'-'*50}")
    
    with pdfplumber.open(pdf_path) as pdf:
        for page in pdf.pages:
            text = page.extract_text()
            if not text:
                continue
                
            lines = text.split('\n')
            
            for line in lines:
                line = line.strip()
                if not line or len(line) < 20:
                    continue
                
                # Skip header lines
                if any(x in line.lower() for x in ['studentid', 'name', 'sciencechoice', 'meanpoints', 'admission', 'adm']):
                    continue
                
                # Split the line into parts
                parts = line.split()
                
                # We need at least 19 parts to have all the data (not 20)
                if len(parts) < 19:
                    continue
                
                # The first part is Student Admission Number (replacing StudentID)
                student_admission_no = parts[0]
                
                # Find where the name ends and choices begin
                name_parts = []
                i = 1  # Start after Student Admission Number
                
                # Keep adding to name until we find a choice field
                while i < len(parts) and parts[i].lower() not in all_choices:
                    name_parts.append(parts[i])
                    i += 1
                
                # If we didn't find any choice fields, skip this line
                if i + 17 > len(parts):  # Need 3 choices + 2 mean fields + 12 scores
                    continue
                
                # Extract the choices (3 fields)
                choices = parts[i:i+3]
                i += 3
                
                # Extract mean points and mean grade
                mean_points = parts[i]
                mean_grade = parts[i+1]
                i += 2
                
                # The remaining parts should be the 12 subject scores
                score_values = parts[i:i+12]
                
                # If we don't have exactly 12 scores, pad with None
                if len(score_values) < 12:
                    score_values.extend([None] * (12 - len(score_values)))
                elif len(score_values) > 12:
                    score_values = score_values[:12]
                
                # Process scores
                processed_scores = []
                for score in score_values:
                    if score and score.lower() == 'nan':
                        processed_scores.append(None)
                    else:
                        try:
                            processed_scores.append(float(score))
                        except:
                            processed_scores.append(None)
                
                # Get the student name
                student_name = " ".join(name_parts).title()
                
                # Add to results - ORDER IS CRITICAL:
                # 1. student_admission_no, 2. student_name, 3. class, 4. stream, 5. exam_name, 6. term
                # 7-18: subject scores in exact order
                # 19: mean_points, 20: mean_grade
                results.append([
                    student_admission_no,   # student_admission_no (now first)
                    student_name,           # student_name (now second)
                    class_name,             # class
                    stream,                 # stream
                    exam_name,              # exam_name
                    term,                   # term
                    *processed_scores,      # 12 subject scores in order
                    mean_points,            # mean_points
                    mean_grade              # mean_grade
                ])
    
    # Generate CSV output - HEADER ORDER MUST MATCH PHP EXPECTATIONS
    # Updated to put student_admission_no first, then student_name
    header = [
        "student_admission_no", "student_name", "class", "stream", "exam_name", "term",
        "english", "math", "kiswahili", "cre", "chemistry",
        "physics", "biology", "geography", "history", "business",
        "agriculture", "computer", "mean_points", "mean_grade"
    ]
    
    # Use csv module for proper CSV formatting
    output = io.StringIO()
    writer = csv.writer(output)
    writer.writerow(header)
    
    for result in results:
        # Ensure each result has exactly 20 columns
        if len(result) != 20:
            # Pad with empty values if needed
            result.extend([''] * (20 - len(result)))
        writer.writerow([str(x) if x is not None else '' for x in result])
    
    return {
        'success': True,
        'csv': output.getvalue(),
        'records_processed': len(results)
    }

@app.route('/parse_exam_pdf', methods=['POST'])
def parse_exam_pdf_endpoint():
    if 'pdf' not in request.files:
        return jsonify({'success': False, 'error': 'No PDF uploaded'}), 400

    pdf_file = request.files['pdf']
    class_name = request.form.get('class')
    stream = request.form.get('stream')
    exam_name = request.form.get('exam_name')
    term = request.form.get('term')
    
    temp_path = 'temp.pdf'
    pdf_file.save(temp_path)

    try:
        result = parse_exam_pdf(temp_path, class_name, stream, exam_name, term)
        return jsonify(result)
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)}), 500
    finally:
        if os.path.exists(temp_path):
            os.remove(temp_path)

def find_free_port(start=5000, max_port=5100):
    for port in range(start, max_port):
        with socket.socket(socket.AF_INET, socket.SOCK_STREAM) as s:
            if s.connect_ex(("127.0.0.1", port)) != 0:
                return port
    raise RuntimeError("No free ports available")

if __name__ == "__main__":
    # Check if we're in CLI mode or Flask mode
    if len(sys.argv) > 1 and sys.argv[1] == '--exam-mode':
        # Exam mode CLI
        parser = argparse.ArgumentParser(description='Parse exam PDF results')
        parser.add_argument('pdf_path', help='Path to the PDF file')
        parser.add_argument('--class', dest='class_name', required=True, help='Class name')
        parser.add_argument('--stream', required=True, help='Stream name')
        parser.add_argument('--exam-name', required=True, help='Exam name')
        parser.add_argument('--term', required=True, help='Term')
        parser.add_argument('--debug', action='store_true', help='Enable debug output')
        
        args = parser.parse_args(sys.argv[2:])
        
        # Enable debug if requested
        if args.debug:
            DEBUG_ENABLED = True
        
        try:
            result = parse_exam_pdf(
                args.pdf_path,
                args.class_name,
                args.stream,
                args.exam_name,
                args.term
            )
            print(json.dumps(result))
        except Exception as e:
            print(json.dumps({
                'success': False,
                'error': f'Failed to parse PDF: {str(e)}'
            }))
        sys.exit(0)
    
    elif len(sys.argv) > 2:  # KCSE mode CLI
        pdf_path = sys.argv[1]
        year = sys.argv[2]
        
        # Enable debug only if requested
        if len(sys.argv) > 3 and sys.argv[3] == '--debug':
            DEBUG_ENABLED = True
        
        try:
            result = extract_data(pdf_path, year)
            # In CLI mode, only output the JSON
            print(json.dumps(result))
        except Exception as e:
            print(json.dumps({"success": False, "error": str(e)}))
        sys.exit(0)

    # Flask mode - disable debug by default
    DEBUG_ENABLED = False
    port = find_free_port(5000, 5100)
    app.run(debug=True, use_reloader=False, port=port)