const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const fs = require('fs');
const baseUrl = process.env.PORTAL_QC_BASE_URL || 'http://127.0.0.1:8000';
const outputDir = process.env.PORTAL_QC_OUTPUT || 'reference/qc/' + new Date().toLocaleDateString('en-CA', { timeZone: 'Asia/Jakarta' });
if (process.env.PORTAL_QC_ALLOW_WRITES !== '1' || !process.env.PORTAL_QC_AUTH_FILE) throw new Error('Use seeded test data with PORTAL_QC_ALLOW_WRITES=1 and PORTAL_QC_AUTH_FILE.');
fs.mkdirSync(outputDir, { recursive: true });
(async () => {
 const browser = await chromium.launch({ headless: true, chromiumSandbox: false, executablePath: process.env.PLAYWRIGHT_CHROMIUM_PATH });
 const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
 const page = await context.newPage();
 const result = { date: new Date().toISOString(), checks: [], errors: [], failedRequests: [], externalRequests: [], fixtures: {} };
 page.on('pageerror', error => result.errors.push(error.message));
 page.on('response', response => { if (response.status() >= 400) result.failedRequests.push({ url: response.url(), status: response.status() }); });
 page.on('request', request => { if (/^https?:/.test(request.url()) && !request.url().startsWith(baseUrl)) result.externalRequests.push(request.url()); });
 const check = (name, pass, details) => { result.checks.push({ name, pass, details }); if (!pass) console.log('FAIL', name, details); };
 const go = path => page.goto(baseUrl + path);
 const screenshot = async name => { await page.evaluate(() => scrollTo(0, 0)); await page.screenshot({ path: `${outputDir}/${name}.png`, fullPage: true }); };
 const readyEditor = async () => { await page.locator('[data-editor-ready="true"]').waitFor(); };
 const boldText = async text => { const editor = page.locator('.ql-editor'); await editor.fill(text); await editor.press('ControlOrMeta+A'); await page.getByRole('button', { name: 'Tebal', exact: true }).click(); };
 const submit = async (name, destination) => { await page.getByRole('button', { name, exact: true }).click(); if (destination) await page.waitForURL(destination); else await page.waitForLoadState('networkidle'); };
 try {
  await go('/'); await page.evaluate(() => document.fonts.ready);
  check('Public page excludes editor bundle', await page.evaluate(() => !performance.getEntriesByType('resource').some(r => r.name.includes('admin-editor'))));
  const fontClient=await context.newCDPSession(page); await fontClient.send('DOM.enable'); await fontClient.send('CSS.enable'); const fontRoot=(await fontClient.send('DOM.getDocument')).root; result.renderedFonts={};
  for(const [selector,family] of [['h1','Manrope'],['.hero-copy p','Inter']]) { const {nodeId}=await fontClient.send('DOM.querySelector',{nodeId:fontRoot.nodeId,selector}); const fontData=await fontClient.send('CSS.getPlatformFontsForNode',{nodeId}); result.renderedFonts[family]=fontData.fonts; check('Actual rendered font '+family,fontData.fonts.some(font=>font.familyName.startsWith(family)&&font.isCustomFont)); }
  check('Reference fonts applied', await page.evaluate(() => getComputedStyle(document.querySelector('h1')).fontFamily.includes('Manrope') && getComputedStyle(document.body).fontFamily.includes('Inter')));
  await go('/admin/login'); const auth = JSON.parse(fs.readFileSync(process.env.PORTAL_QC_AUTH_FILE, 'utf8'));
  await page.locator('#email').fill(auth.email); await page.locator('#password').fill(auth.password); await submit('Masuk ke Panel', '**/admin');
  check('Dashboard concise heading', await page.getByRole('heading', { level: 1, name: 'Dashboard', exact: true }).isVisible());
  for (const path of ['content/pages','content/posts','content/sections','content/menus','content/media','content/settings','forms','accounts','masters/programs','masters/cohorts','masters/categories','masters/services','templates']) {
   const response = await go('/admin/' + path);
   check('Table and filter: ' + path, response.status() === 200 && await page.locator('table').isVisible() && await page.locator('#list-search').isVisible());
   check('Breadcrumb and no inline editor: ' + path, await page.getByRole('navigation', { name: 'Breadcrumb', exact: true }).isVisible() && await page.locator('.content-tabs').count() === 0 && await page.locator('#main textarea').count() === 0);
  }
  check('Sidebar no independent scroll', await page.locator('#admin-sidebar').evaluate(e => getComputedStyle(e).overflowY === 'visible'));
  await screenshot('admin-standard-templates');
  await go('/admin/content/pages'); await page.getByRole('link', { name: 'Tambah', exact: true }).click(); await page.waitForURL('**/admin/content/pages/create'); await readyEditor();
  check('Local WYSIWYG initialized', await page.locator('.ql-toolbar').isVisible());
  const stamp = Date.now(); result.fixtures.pageSlug = 'qc-admin-standard-page-' + stamp;
  await page.locator('[name="title"]').fill('QC Admin Standard Page'); await page.locator('[name="slug"]').fill(result.fixtures.pageSlug); await boldText('Informasi penting dari editor lokal.'); await page.locator('[name="status"]').selectOption('published');
  await screenshot('admin-standard-page-create'); await submit('Simpan Perubahan', '**/admin/content/pages/*/edit'); await readyEditor();
  result.fixtures.pageId = Number(page.url().match(/pages\/(\d+)\/edit/)[1]);
  check('Page saved to separate edit screen', await page.locator('.ql-editor strong').innerText() === 'Informasi penting dari editor lokal.');
  await go('/halaman/' + result.fixtures.pageSlug); check('Formatted content visible publicly', await page.locator('.rich-content strong').innerText() === 'Informasi penting dari editor lokal.');
  check('Public rich content excludes editor assets', await page.evaluate(() => !performance.getEntriesByType('resource').some(r => r.name.includes('admin-editor'))));
  await go('/admin/content/pages/' + result.fixtures.pageId + '/edit'); await readyEditor(); await page.locator('[name="title"]').fill('QC Admin Standard Page Updated'); await submit('Simpan Perubahan');
  await go('/admin/content/pages?q=' + encodeURIComponent(result.fixtures.pageSlug) + '&status=published'); check('Page search with status filter', await page.locator('tbody tr').count() === 1 && await page.locator('tbody').innerText().then(t => t.includes('Updated'))); await screenshot('admin-standard-pages');
  await go('/admin/content/pages?q=not-found-qc-12345'); check('Empty list state', await page.getByText('Tidak ada data yang sesuai.', { exact: true }).isVisible());
  await go('/admin/accounts/create'); await page.locator('[name="name"]').fill('QC Admin Standard Officer'); result.fixtures.accountEmail = `qc-admin-standard-${stamp}@example.test`; await page.locator('[name="email"]').fill(result.fixtures.accountEmail); await page.locator('[name="password"]').fill('Temporary-QC-Password-2026'); await page.getByLabel('Editor', { exact: true }).check(); await page.getByLabel('Kelola konten', { exact: true }).check(); await submit('Simpan Akun', '**/admin/accounts/*/edit'); result.fixtures.accountId = Number(page.url().match(/accounts\/(\d+)\/edit/)[1]); check('Account separate create/edit', await page.locator('[name="email"]').inputValue() === result.fixtures.accountEmail);
  await go('/admin/masters/programs/create'); await page.locator('[name="name"]').fill('QC Admin Standard Program'); result.fixtures.programCode = 'QC-' + String(stamp).slice(-8); await page.locator('[name="code"]').fill(result.fixtures.programCode); await submit('Simpan Perubahan', '**/admin/masters/programs/*/edit'); result.fixtures.programId = Number(page.url().match(/programs\/(\d+)\/edit/)[1]); check('Master separate create/edit', await page.locator('[name="code"]').inputValue() === result.fixtures.programCode);
  await go('/admin/forms/create'); await page.locator('[name="title"]').fill('QC Admin Standard Form'); result.fixtures.formSlug = 'qc-admin-standard-form-' + stamp; await page.locator('[name="slug"]').fill(result.fixtures.formSlug); await submit('Buat Draft', '**/admin/forms/*'); result.fixtures.formId = Number(page.url().match(/forms\/(\d+)$/)[1]); check('Form create opens builder', await page.locator('[data-builder]').isVisible());
  await go('/admin/templates/create'); await readyEditor(); await page.locator('[name="name"]').fill('QC Admin Standard Template'); await boldText('Konfirmasi {{full_name}} untuk pengajuan {{reference}}.'); await page.locator('[name="signatory"]').fill('Pejabat Uji QC Admin'); await page.locator('[name="title"]').fill('Bagian Kemahasiswaan'); await submit('Simpan Template', '**/admin/templates/*/edit'); await readyEditor(); result.fixtures.templateId = Number(page.url().match(/templates\/(\d+)\/edit/)[1]); check('Template rich text and separate edit', await page.locator('.ql-editor strong').isVisible());
  await go('/layanan/beasiswa/beasiswa-umum'); await page.locator('#student_number').fill('00992233'); await page.locator('#full_name').fill('QC Admin Standard Student'); await page.locator('#study_program_id').selectOption({ index: 1 }); await page.locator('#cohort_id').selectOption({ index: 1 }); await page.locator('#reply_channel').selectOption('whatsapp'); await page.locator('#whatsapp').fill('081234567890'); await page.locator('#field_program').selectOption('Prestasi'); await page.locator('[name="consent"]').check(); await submit('Kirim Pengajuan', '**/akses/*'); const reference = await page.locator('#application-reference').innerText();
  await go('/admin/applications?q=' + reference); await page.getByRole('link', { name: reference, exact: true }).click(); result.fixtures.applicationId = Number(page.url().match(/applications\/(\d+)$/)[1]); await page.locator('#status').selectOption('under_review'); await submit('Simpan Tindak Lanjut'); await page.locator('#status').selectOption('approved'); await submit('Simpan Tindak Lanjut'); await page.locator('#template_id').selectOption(String(result.fixtures.templateId)); await page.locator('#decision').fill('Dokumen uji QC, bukan surat resmi.'); await submit('Pratinjau Surat');
  check('Rich template PDF preview', await page.locator('iframe[title="Pratinjau PDF A4"]').isVisible()); await page.locator('[name="confirmed"]').check(); await submit('Terbitkan Surat PDF'); const pending = page.waitForEvent('download'); await page.locator('a[href*="/admin/letters/"]').first().click(); const download = await pending; await download.saveAs('/tmp/admin-standard-rich-letter.pdf'); check('Rich template PDF download', fs.readFileSync('/tmp/admin-standard-rich-letter.pdf').subarray(0,8).toString() === '%PDF-1.4');
  await page.setViewportSize({ width: 1440, height: 600 }); await go('/admin/masters/programs'); check('Short viewport keeps sidebar accessible', await page.locator('#admin-sidebar').evaluate(e => getComputedStyle(e).overflowY === 'visible' && Math.ceil(e.getBoundingClientRect().height) >= e.scrollHeight));
  await page.setViewportSize({ width: 390, height: 844 }); await go('/admin/content/pages?q=QC%20Admin%20Standard'); check('Mobile list has no page overflow', await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)); await screenshot('admin-standard-mobile-list');
  await page.getByRole('button', { name: 'Buka menu admin', exact: true }).click(); check('Mobile sidebar opens', await page.locator('#admin-sidebar').isVisible()); await page.keyboard.press('Escape');
  await go('/admin/content/pages/' + result.fixtures.pageId + '/edit'); await readyEditor(); check('Mobile WYSIWYG fits viewport', await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)); await screenshot('admin-standard-mobile-editor');
  check('No external API or font requests', result.externalRequests.length === 0, result.externalRequests);
 } catch(error) { result.errors.push(error.message); await screenshot('admin-standard-failure'); }
 fs.writeFileSync(`${outputDir}/admin-standard-results.json`,JSON.stringify(result,null,2)); console.log(JSON.stringify(result,null,2)); await browser.close(); if(result.errors.length || result.failedRequests.length || result.checks.some(c => !c.pass)) process.exitCode = 1;
})().catch(error => { console.error(error); process.exit(1); });
