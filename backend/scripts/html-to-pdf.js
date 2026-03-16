#!/usr/bin/env node
/**
 * HTML to PDF converter using Puppeteer
 * Usage: node html-to-pdf.js <input.html> <output.pdf> [options]
 * Options: --landscape, --format=A4
 * Or pipe HTML via stdin: echo "<html>...</html>" | node html-to-pdf.js - output.pdf
 */

const puppeteer = require('puppeteer');
const fs = require('fs');
const path = require('path');

async function main() {
  const args = process.argv.slice(2);
  const inputFile = args[0];
  const outputFile = args[1] || '/dev/stdout';

  const isLandscape = args.includes('--landscape');
  const formatArg = args.find(a => a.startsWith('--format='));
  const format = formatArg ? formatArg.split('=')[1] : 'A4';

  let html;
  if (inputFile === '-') {
    // Read from stdin
    html = '';
    for await (const chunk of process.stdin) {
      html += chunk;
    }
  } else {
    html = fs.readFileSync(inputFile, 'utf-8');
  }

  const browser = await puppeteer.launch({
    executablePath: process.env.PUPPETEER_EXECUTABLE_PATH || '/usr/bin/chromium',
    headless: true,
    args: [
      '--no-sandbox',
      '--disable-setuid-sandbox',
      '--disable-dev-shm-usage',
      '--disable-gpu',
      '--font-render-hinting=none',
    ],
  });

  const page = await browser.newPage();
  await page.setContent(html, { waitUntil: 'networkidle0', timeout: 30000 });

  const pdfBuffer = await page.pdf({
    format,
    landscape: isLandscape,
    margin: {
      top: '15mm',
      bottom: '15mm',
      left: '18mm',
      right: '18mm',
    },
    printBackground: true,
    preferCSSPageSize: true,
  });

  if (outputFile === '/dev/stdout') {
    process.stdout.write(pdfBuffer);
  } else {
    fs.writeFileSync(outputFile, pdfBuffer);
  }

  await browser.close();
}

main().catch(err => {
  console.error('PDF generation failed:', err.message);
  process.exit(1);
});
