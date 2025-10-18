// detect-env.js (module)
export async function detectBrowser() {
  const nav = navigator || {};
  const ua = (nav.userAgent || "").toLowerCase();
  const uaData = nav.userAgentData || null;
  const braveViaNavigator = !!(nav.brave && typeof nav.brave.isBrave === "function") &&
    (await nav.brave.isBrave().catch(() => false)) === true;
  let brandName = null;
  if (uaData && Array.isArray(uaData.brands)) {
    for (const b of uaData.brands) {
      const n = (b.brand || "").toLowerCase();
      if (n.includes("brave")) { brandName = "Brave"; break; }
      if (n.includes("edge")) { brandName = "Edge"; break; }
      if (n.includes("opera") || n.includes("opr")) { brandName = "Opera"; break; }
      if (n.includes("chrome")) { brandName = "Chrome"; }
    }
  }
  const isEdge = /edg\//.test(ua);
  const isOpera = /opr\//.test(ua) || /opera/.test(ua);
  const isFirefox = /firefox\//.test(ua);
  const isSafari = /^((?!chrome|android).)*safari/.test(ua);
  const isChromeFamily = /chrome\//.test(ua) || /crios\//.test(ua) || /chromium\//.test(ua);
  let browser = "Unknown";
  if (braveViaNavigator || (brandName === "Brave") || /\bbrave\//.test(ua)) browser = "Brave";
  else if (brandName === "Edge" || isEdge) browser = "Edge";
  else if (isOpera || brandName === "Opera") browser = "Opera";
  else if (isFirefox) browser = "Firefox";
  else if (isSafari) browser = "Safari";
  else if (isChromeFamily || brandName === "Chrome") browser = "Chrome";
  let version = null;
  if (uaData && typeof uaData.getHighEntropyValues === "function") {
    try {
      const { fullVersionList } = await uaData.getHighEntropyValues(["fullVersionList"]);
      if (Array.isArray(fullVersionList)) {
        const wanted = (browser === "Brave") ? "Brave" : browser;
        const match = fullVersionList.find(b => (b.brand || "").toLowerCase().includes((wanted || "").toLowerCase()))
                  || fullVersionList.find(b => !/^not/.test((b.brand || "").toLowerCase()));
        if (match) version = match.version || null;
      }
    } catch {}
  }
  if (!version) {
    const rx = { Brave:/\bbrave\/([\d.]+)/, Edge:/\bedg\/([\d.]+)/, Opera:/\b(?:opr|opera)\/([\d.]+)/,
                 Firefox:/\bfirefox\/([\d.]+)/, Safari:/\bversion\/([\d.]+)\s+safari/, Chrome:/\bchrome\/([\d.]+)/ }[browser];
    if (rx) { const m = ua.match(rx); version = m ? m[1] : null; }
  }
  return { name: browser, version };
}
export async function detectOS() {
  const nav = navigator || {};
  const ua = (nav.userAgent || ""); const uaLower = ua.toLowerCase();
  const uaData = nav.userAgentData || null;
  const platform = (uaData && uaData.platform) || nav.platform || "";
  let os = "Unknown";
  if (/windows/i.test(platform) || /windows nt/i.test(ua)) os = "Windows";
  else if (/mac/i.test(platform) || /(mac os x|darwin)/i.test(ua)) os = "macOS";
  else if (/android/i.test(platform) || /android/i.test(ua)) os = "Android";
  else if (/ios|iphone|ipad|ipod/i.test(platform) || /(iPhone|iPad|iPod)/i.test(ua)) os = "iOS";
  else if (/linux/i.test(platform) || /x11; linux/i.test(ua)) os = "Linux";
  let distro = null;
  if (/linux/i.test(os)) {
    const tokens = [
      { name:"Linux Mint", rx:/\blinux\s+mint[\/\s]?([\d.]*)/i },
      { name:"Ubuntu", rx:/\bubuntu[\/\s]?([\d.]*)/i },
      { name:"Debian", rx:/\bdebian[\/\s]?([\d.]*)/i },
      { name:"Fedora", rx:/\bfedora[\/\s]?([\d.]*)/i },
      { name:"Arch", rx:/\barch\b/i },
      { name:"Manjaro", rx:/\bmanjaro\b/i },
      { name:"openSUSE", rx:/\bopensuse\b/i },
      { name:"elementary OS", rx:/\belementary\b/i },
      { name:"Pop!_OS", rx:/\bpop[_!\s]?os\b/i },
      { name:"Gentoo", rx:/\bgentoo\b/i }
    ];
    for (const t of tokens) {
      const m = uaLower.match(t.rx);
      if (m) { distro = t.name + (m[1] ? ` ${m[1]}` : ""); break; }
    }
  }
  return { name: os, distro };
}
export async function detectEnv(){ const [browser, os]=await Promise.all([detectBrowser(),detectOS()]); return {browser,os}; }
(async function attach(){
  try {
    const env = await detectEnv();
    window.__teEnv = env;
    const root = document.documentElement;
    if (env.browser?.name) root.dataset.browser = env.browser.name.toLowerCase();
    if (env.os?.name) root.dataset.os = env.os.name.toLowerCase();
    if (env.os?.distro) root.dataset.distro = env.os.distro.toLowerCase().replace(/\s+/g,'-').replace(/[!_]/g,'-');
  } catch {}
})();