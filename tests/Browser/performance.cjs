const baseUrl = process.env.PORTAL_QC_BASE_URL || 'http://127.0.0.1:8000';
const outputDir = process.env.PORTAL_QC_OUTPUT || 'reference/qc/' + new Date().toLocaleDateString('en-CA', { timeZone: 'Asia/Jakarta' });
require('fs').mkdirSync(outputDir, { recursive: true });
const {chromium}=require(process.env.PLAYWRIGHT_MODULE || 'playwright');const fs=require('fs');
(async()=>{const browser=await chromium.launch({headless:true,chromiumSandbox:false,executablePath:process.env.PLAYWRIGHT_CHROMIUM_PATH});const results=[];
for(const profile of [{name:'desktop',width:1440,height:1000},{name:'mobile',width:390,height:844},{name:'small-mobile',width:360,height:800},{name:'tablet',width:768,height:1024},{name:'mobile-slow4g',width:390,height:844,slow:true}]) {
 const context=await browser.newContext({viewport:{width:profile.width,height:profile.height}}); const page=await context.newPage();
 await page.addInitScript(()=>{window.qcVitals={lcp:0,cls:0};new PerformanceObserver(list=>{for(const e of list.getEntries())window.qcVitals.lcp=e.startTime}).observe({type:'largest-contentful-paint',buffered:true});new PerformanceObserver(list=>{for(const e of list.getEntries())if(!e.hadRecentInput)window.qcVitals.cls+=e.value}).observe({type:'layout-shift',buffered:true});});
 if(profile.slow){const client=await context.newCDPSession(page);await client.send('Network.enable');await client.send('Network.emulateNetworkConditions',{offline:false,latency:150,downloadThroughput:200000,uploadThroughput:93750});await client.send('Emulation.setCPUThrottlingRate',{rate:4});}
 await page.goto(`${baseUrl}`);await page.evaluate(()=>document.fonts.ready);await page.waitForTimeout(800);
 const data=await page.evaluate(()=>{const n=performance.getEntriesByType('navigation')[0];return {...window.qcVitals,ttfb:n.responseStart,dcl:n.domContentLoadedEventEnd,load:n.loadEventEnd,transferBytes:n.transferSize+performance.getEntriesByType('resource').reduce((s,r)=>s+r.transferSize,0),overflow:document.documentElement.scrollWidth>innerWidth,art:(()=>{const e=document.querySelector('.hero-art');const r=e.getBoundingClientRect();return {left:r.left,right:r.right,width:r.width,css:getComputedStyle(e).transform}})()};});
 results.push({profile:profile.name,...data});
 if(profile.name==='mobile') {await page.evaluate(()=>scrollTo(0,0));await page.screenshot({path:`${outputDir}/home-mobile.png`,fullPage:true});}
 if(profile.name==='desktop'){await page.keyboard.press('Tab');results.push({profile:'keyboard',skipLinkFocused:await page.locator('.skip-link').evaluate(e=>e===document.activeElement)});await page.keyboard.press('Enter');await page.locator('.faq-list summary').first().focus();await page.keyboard.press('Enter');results.push({profile:'keyboard-faq',opened:await page.locator('.faq-list details').first().getAttribute('open')!==null});}
 await context.close();
}
fs.writeFileSync(`${outputDir}/performance-results.json`,JSON.stringify(results,null,2));console.log(JSON.stringify(results,null,2));await browser.close();})().catch(e=>{console.error(e);process.exit(1)});
