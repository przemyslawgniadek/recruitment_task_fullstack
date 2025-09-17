# Manual Testing Checklist - Telemedi FX Desk

## 🎯 **Testing Environment Setup**

### Prerequisites
- [ ] Docker containers running (`docker-compose up -d`)
- [ ] Backend accessible at `http://localhost/api/`
- [ ] Frontend accessible at `http://localhost/`
- [ ] NBP API connectivity verified
- [ ] Browser Developer Tools open (F12)

---

## 🏠 **1. APPLICATION STARTUP & NAVIGATION**

### Initial Load
- [ ] **Page loads without errors** (check console for JS errors)
- [ ] **Default redirect to `/dashboard`** works
- [ ] **Navigation bar displays** with correct links
- [ ] **Telemedi FX Desk branding** visible

### Navigation Testing
- [ ] **Dashboard link** (`💱 Dashboard`) navigates to `/dashboard`
- [ ] **History link** (`📈 History`) navigates to `/history`
- [ ] **Setup Check link** (`🔧 Setup Check`) navigates to `/setup-check`
- [ ] **Browser back/forward buttons** work correctly
- [ ] **Direct URL access** works for all routes

**Expected Results:**
- ✅ Clean navigation without 404 errors
- ✅ Consistent navbar across all pages
- ✅ Proper URL updates in address bar

---

## 💱 **2. DASHBOARD COMPONENT TESTING**

### Data Loading
- [ ] **Loading spinner** appears during initial data fetch
- [ ] **"Loading Exchange Rates..."** message displays
- [ ] **Data loads within 3 seconds** (normal conditions)
- [ ] **5 currencies displayed** (EUR, USD, CZK, IDR, BRL)

### Currency Table Verification
- [ ] **Flag icons** display correctly (🇪🇺 🇺🇸 🇨🇿 🇮🇩 🇧🇷)
- [ ] **Currency names** match flags
- [ ] **Mid rates** are numeric and reasonable (e.g., EUR ~4.25 PLN)
- [ ] **Buy rates** show for EUR/USD, "Not available" for others
- [ ] **Sell rates** show for all currencies
- [ ] **Margins** display correctly (+/- values)
- [ ] **Operations badges** show "Buy & Sell" or "Sell Only"
- [ ] **History buttons** (📈 History) present for all currencies

### Date Picker Testing
- [ ] **Date picker** shows today's date by default
- [ ] **Min date** is 14 days ago (cannot select older)
- [ ] **Max date** is today (cannot select future)
- [ ] **Date change** triggers new API call
- [ ] **Loading state** during date change
- [ ] **Data updates** after date selection

### Interactive Elements
- [ ] **Refresh button** (🔄) triggers data reload
- [ ] **History buttons** navigate to `/history/{currency}`
- [ ] **Hover effects** on table rows
- [ ] **Responsive design** on mobile/tablet

**Test Data Validation:**
```
EUR: Mid ~4.25, Buy ~4.10, Sell ~4.36
USD: Mid ~3.59, Buy ~3.44, Sell ~3.70
CZK: Mid ~0.17, Buy: null, Sell ~0.37
IDR: Mid ~0.0002, Buy: null, Sell ~0.20
BRL: Mid ~0.68, Buy: null, Sell ~0.87
```

---

## 📈 **3. HISTORY VIEW COMPONENT TESTING**

### Navigation to History
- [ ] **Dashboard History button** opens `/history/{currency}`
- [ ] **Navbar History link** opens `/history` (EUR default)
- [ ] **Direct URL access** `/history/USD` works
- [ ] **Currency parameter** reflected in URL

### Chart Rendering
- [ ] **SVG chart** renders without errors
- [ ] **14-day data** displayed (or available days)
- [ ] **Three lines visible:**
  - [ ] **Blue solid line** (Mid rate)
  - [ ] **Green dashed line** (Buy rate, EUR/USD only)
  - [ ] **Red dotted line** (Sell rate)
- [ ] **Data points** (circles) on each line
- [ ] **Grid lines** with value labels
- [ ] **Date labels** on X-axis (rotated)
- [ ] **Legend** shows line types correctly

### Currency Selector
- [ ] **Dropdown** shows all 5 currencies with flags
- [ ] **Currency change** triggers new API call
- [ ] **URL updates** when currency changes
- [ ] **Chart redraws** with new data
- [ ] **Loading state** during currency switch

### Data Validation
- [ ] **Chronological order** (oldest to newest)
- [ ] **Reasonable value ranges** (no extreme outliers)
- [ ] **Buy rates null** for CZK/IDR/BRL
- [ ] **Consistent data** with Dashboard current rates

### Error Scenarios
- [ ] **Network error** shows retry button
- [ ] **NBP rate limiting** (429) shows appropriate message
- [ ] **No data available** shows empty state
- [ ] **Invalid currency** in URL handled gracefully

---

## 🔧 **4. SETUP CHECK COMPONENT TESTING**

### API Connectivity Tests
- [ ] **Health Check** shows "✅ All systems operational"
- [ ] **Exchange Rates** shows "✅ X currencies available"
- [ ] **Currencies** shows "✅ X currencies configured"
- [ ] **Connection Status** updates every 30s
- [ ] **Manual test buttons** work

### Error Handling
- [ ] **Backend down** shows connection issues
- [ ] **NBP API issues** detected and reported
- [ ] **Timeout scenarios** handled gracefully
- [ ] **Retry mechanisms** work

---

## 🚨 **5. ERROR HANDLING & EDGE CASES**

### Network Issues
- [ ] **Offline mode** - disconnect internet
  - [ ] Error messages display
  - [ ] Retry buttons available
  - [ ] No crashes or infinite loops
- [ ] **Slow connection** - throttle network
  - [ ] Loading states persist
  - [ ] Timeouts handled properly

### Backend Issues
- [ ] **Stop backend container** (`docker-compose stop webserver`)
  - [ ] Connection errors displayed
  - [ ] User-friendly error messages
  - [ ] No JavaScript console errors
- [ ] **Restart backend** (`docker-compose start webserver`)
  - [ ] Automatic recovery works
  - [ ] Data loads correctly

### Invalid Data Scenarios
- [ ] **Future dates** in date picker blocked
- [ ] **Old dates** (>14 days) blocked
- [ ] **Invalid currency** in URL redirects gracefully
- [ ] **Malformed API responses** handled

### Browser Compatibility
- [ ] **Chrome** - all features work
- [ ] **Firefox** - all features work
- [ ] **Safari** (if available) - all features work
- [ ] **Mobile browsers** - responsive design

---

## 📱 **6. RESPONSIVE DESIGN TESTING**

### Desktop (1920x1080)
- [ ] **Full table** visible without scrolling
- [ ] **Chart** displays at full width
- [ ] **Navigation** horizontal layout

### Tablet (768x1024)
- [ ] **Table** remains readable
- [ ] **Chart** scales appropriately
- [ ] **Navigation** collapses properly

### Mobile (375x667)
- [ ] **Table** scrollable horizontally
- [ ] **Chart** fits screen width
- [ ] **Navigation** hamburger menu (if implemented)
- [ ] **Touch interactions** work

---

## ⚡ **7. PERFORMANCE TESTING**

### Load Times
- [ ] **Initial page load** < 3 seconds
- [ ] **API calls** < 1 second (local)
- [ ] **Chart rendering** < 500ms
- [ ] **Navigation** instantaneous

### Memory Usage
- [ ] **No memory leaks** during navigation
- [ ] **Reasonable RAM usage** (check DevTools)
- [ ] **No infinite API calls**

### Caching
- [ ] **Repeated requests** use cache
- [ ] **Fresh data** when needed
- [ ] **No stale data** issues

---

## 🔒 **8. SECURITY & DATA VALIDATION**

### Input Validation
- [ ] **Date inputs** properly validated
- [ ] **URL parameters** sanitized
- [ ] **No XSS vulnerabilities** in displayed data

### API Security
- [ ] **No direct NBP calls** from frontend
- [ ] **All requests** go through backend
- [ ] **Proper error handling** without exposing internals

---

## 📊 **9. DATA ACCURACY TESTING**

### Cross-Reference with NBP
1. **Manual NBP API check:**
   ```bash
   curl "https://api.nbp.pl/api/exchangerates/tables/A/?format=json"
   ```
2. **Compare with Dashboard data:**
   - [ ] **EUR mid rate** matches NBP
   - [ ] **USD mid rate** matches NBP
   - [ ] **Date** matches NBP effective date

### Business Rules Validation
- [ ] **EUR/USD buy rate** = mid - 0.15 PLN
- [ ] **EUR/USD sell rate** = mid + 0.11 PLN
- [ ] **CZK/IDR/BRL buy rate** = null
- [ ] **CZK/IDR/BRL sell rate** = mid + 0.20 PLN

---

## 🎯 **10. USER EXPERIENCE TESTING**

### Workflow Testing
1. **Currency Desk Worker Scenario:**
   - [ ] Check today's EUR rate
   - [ ] View EUR 14-day history
   - [ ] Compare with USD trends
   - [ ] Check if CZK supports buying
   - [ ] Verify margin calculations

2. **Error Recovery Scenario:**
   - [ ] Encounter network error
   - [ ] Use retry mechanism
   - [ ] Continue normal workflow
   - [ ] No data loss or confusion

### Usability
- [ ] **Intuitive navigation** - no training needed
- [ ] **Clear visual hierarchy** - important info prominent
- [ ] **Consistent design** - matching colors/fonts
- [ ] **Helpful error messages** - actionable guidance

---

## ✅ **11. ACCEPTANCE CRITERIA CHECKLIST**

### Functional Requirements
- [ ] **5 currencies supported** (EUR, USD, CZK, IDR, BRL)
- [ ] **Correct buy/sell rules** implemented
- [ ] **14-day history** available
- [ ] **Real-time NBP data** integration
- [ ] **Professional UI** suitable for currency desk

### Technical Requirements
- [ ] **React + TypeScript** implementation
- [ ] **No direct NBP calls** from frontend
- [ ] **Proper error handling** throughout
- [ ] **Responsive design** for all devices
- [ ] **Clean code** with TypeScript types

### Performance Requirements
- [ ] **Fast loading** times
- [ ] **Efficient caching** strategy
- [ ] **No memory leaks**
- [ ] **Smooth interactions**

---

## 📋 **TESTING EXECUTION TEMPLATE**

### Test Session Info
- **Date:** ___________
- **Tester:** ___________
- **Browser:** ___________
- **Environment:** ___________

### Results Summary
- **Total Tests:** _____ / _____
- **Passed:** _____
- **Failed:** _____
- **Blocked:** _____

### Critical Issues Found
1. ________________________________
2. ________________________________
3. ________________________________

### Recommendations
1. ________________________________
2. ________________________________
3. ________________________________

---

## 🚀 **SIGN-OFF CRITERIA**

**Application is ready for production when:**
- [ ] **All critical tests pass** (100%)
- [ ] **No blocking issues** remain
- [ ] **Performance acceptable** (<3s load times)
- [ ] **Cross-browser compatibility** verified
- [ ] **Mobile responsiveness** confirmed
- [ ] **Error handling** comprehensive
- [ ] **Data accuracy** validated against NBP

**Tester Signature:** _____________________ **Date:** _________

**Developer Sign-off:** _____________________ **Date:** _________
