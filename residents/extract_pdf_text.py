#!/usr/bin/env python3
"""
PDF Text Extraction Script for Filipino Dishes
Extracts text from PDF files for NLP processing
"""

import sys
import os

def extract_text_pdfplumber(pdf_path):
    """Extract text using pdfplumber library"""
    try:
        import pdfplumber
        text = ""
        with pdfplumber.open(pdf_path) as pdf:
            for page in pdf.pages:
                text += page.extract_text() or ""
        return text
    except ImportError:
        return None
    except Exception as e:
        return None

def extract_text_pypdf2(pdf_path):
    """Extract text using PyPDF2 library"""
    try:
        import PyPDF2
        text = ""
        with open(pdf_path, 'rb') as file:
            pdf_reader = PyPDF2.PdfReader(file)
            for page in pdf_reader.pages:
                text += page.extract_text() or ""
        return text
    except ImportError:
        return None
    except Exception as e:
        return None

def extract_text_pdfminer(pdf_path):
    """Extract text using pdfminer library"""
    try:
        from pdfminer.high_level import extract_text
        return extract_text(pdf_path)
    except ImportError:
        return None
    except Exception as e:
        return None

if __name__ == "__main__":
    if len(sys.argv) < 2:
        sys.exit(1)
    
    pdf_path = sys.argv[1]
    
    if not os.path.exists(pdf_path):
        sys.exit(1)
    
    # Try different PDF extraction methods
    text = extract_text_pdfplumber(pdf_path)
    if not text:
        text = extract_text_pypdf2(pdf_path)
    if not text:
        text = extract_text_pdfminer(pdf_path)
    
    if text:
        print(text)
    else:
        sys.exit(1)

