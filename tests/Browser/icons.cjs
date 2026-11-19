const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const fs = require('fs');
const baseUrl = process.env.PORTAL_QC_BASE_URL || 'http://127.0.0.1:8000';
const outputDir = process.env.PORTAL_QC_OUTPUT || 'reference/qc/' + new Date().toLocaleDateString('en-CA', { timeZone: 'Asia/Jakarta' });
if (!process.env.PORTAL_QC_AUTH_FILE || !process.env.PORTAL_QC_FIXTURE_FILE) throw new Error('Use a seeded test environment and provide QC credential/fixture files.');
fs.mkdirSync(outputDir, { recursive: true });
(async () => {
 const browser = await chromium.launch({ headless: true, chromiumSandbox: false, executablePath: process.env.PLAYWRIGHT_CHROMIUM_PATH });
 const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
 const page = await context.newPage();
 const result = { date: new Date().toISOString(), checks: [], errors: [], failedRequests: [], externalRequests: [] };
 page.on('pageerror', error => result.errors.push(error.message));
 page.on('response', response => { if(response.status() >= 400) result.failedRequests.push({ url: response.url(), status: response.status() }); });
 page.on('request', request => { if(/^https?:/.test(request.url()) && !request.url().startsWith(baseUrl)) result.externalRequests.push(request.url()); });
 const check = (name, pass, details) => { result.checks.push({ name, pass, details }); if(!pass) console.log('FAIL', name, details); };
 const fixtures = JSON.parse(fs.readFileSync(process.env.PORTAL_QC_FIXTURE_FILE, 'utf8'));
 const go = async path => { await page.goto(baseUrl + path); await page.locator('svg.lucide').first().waitFor({ state: 'attached' }); };
 try {
  await go('/'); await page.evaluate(() => document.fonts.ready); await page.waitForTimeout(1000);
  check('Public SVG icons rendered by Lucide', await page.locator('svg.lucide').count() > 20);
  check('No unresolved public icon placeholders', await page.locator('i[data-lucide]').count() === 0);
  check('Service card icons present', await page.locator('.service-card svg.lucide').count() === 8);
  await page.screenshot({ path: `${outputDir}/lucide-public-desktop.png`, fullPage: true });
  await go('/admin/login'); const auth = JSON.parse(fs.readFileSync(process.env.PORTAL_QC_AUTH_FILE, 'utf8'));
  await page.locator('#email').fill(auth.email); await page.locator('#password').fill(auth.password); await page.getByRole('button', { name:'Masuk ke Panel', exact:true }).click(); await page.waitForURL('**/admin');
  check('Admin sidebar uses Lucide', await page.locator('.admin-sidebar svg.lucide').count() >= 12);
  for(const path of ['content/pages','content/posts','forms','accounts','masters/programs','templates']) {
   await go('/admin/' + path);
   const edits = page.locator('.table-edit-link');
   check('Pencil edit buttons: ' + path, await edits.count() > 0 && await edits.locator('svg.lucide-pencil').count() === await edits.count());
   check('Accessible edit buttons: ' + path, await edits.evaluateAll(items => items.every(item => item.getAttribute('aria-label')?.startsWith('Edit ') && item.title.startsWith('Edit '))));
  }
  await go('/admin/content/pages?q=QC%20Lucide'); await page.screenshot({ path: `${outputDir}/lucide-admin-edit-buttons.png`, fullPage:true });
  await page.locator('.table-edit-link').first().click(); await page.waitForURL('**/admin/content/pages/*/edit'); await page.locator('[data-editor-ready="true"]').waitFor();
  check('Pencil button opens correct edit page', page.url().endsWith(`/pages/${fixtures.pageId}/edit`));
  check('WYSIWYG toolbar uses Lucide', await page.locator('.ql-toolbar button svg.lucide').count() === 7);
  await page.locator('.ql-editor').fill('Ikon toolbar editor lokal.'); await page.locator('.ql-editor').press('ControlOrMeta+A'); await page.getByRole('button', { name:'Tebal', exact:true }).click();
  check('Rich text action still works with new icons', await page.locator('textarea[name="body"]').inputValue().then(value => value.includes('<strong>')));
  await page.screenshot({ path: `${outputDir}/lucide-editor-toolbar.png`, fullPage:true });
  await go('/admin/forms/' + fixtures.formId); await page.getByRole('button', { name:'Tambah Field', exact:true }).click();
  const field = page.locator('[data-builder-field]').last();
  check('Builder dynamically added icons rendered', await field.locator('svg.lucide').count() === 3 && await field.locator('i[data-lucide]').count() === 0);
  await field.getByRole('button', { name:'Hapus', exact:true }).click(); check('Dynamic remove button works', await page.locator('[data-builder-field]').count() === 0);
  await page.setViewportSize({ width:390, height:844 }); await go('/admin/content/pages?q=QC%20Lucide');
  check('Mobile edit icons fit viewport', await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
  await page.getByRole('button', { name:'Buka menu admin', exact:true }).click(); check('Mobile menu with Lucide opens', await page.locator('.admin-sidebar').isVisible()); await page.keyboard.press('Escape');
  await page.screenshot({ path: `${outputDir}/lucide-admin-mobile.png`, fullPage:true });
  await go('/'); await page.getByRole('button', { name:'Buka menu navigasi', exact:true }).click(); check('Public mobile menu icon works', await page.locator('#main-nav').isVisible()); await page.keyboard.press('Escape');
  await page.locator('.faq-list summary').first().click(); check('FAQ icon changes with open state', await page.locator('.faq-list details').first().evaluate(e => e.open && getComputedStyle(e.querySelector('.faq-toggle')).transform !== 'none'));
  for(const width of [360,768,1440]) { await page.setViewportSize({width,height:900}); await go('/'); check('Public icons no overflow '+width, await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)); }
  check('No external icon CDN requests', result.externalRequests.length === 0);
 } catch(error) { result.errors.push(error.message); await page.screenshot({path:`${outputDir}/lucide-failure.png`,fullPage:true}); }
 fs.writeFileSync(`${outputDir}/lucide-results.json`,JSON.stringify(result,null,2)); console.log(JSON.stringify(result,null,2)); await browser.close();
 if(result.errors.length || result.failedRequests.length || result.checks.some(item => !item.pass)) process.exitCode=1;
})().catch(error => { console.error(error); process.exit(1); });
