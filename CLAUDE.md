# Hesabix — کدبیس

نرم‌افزار حسابداری آنلاین ایرانی با معماری Full-Stack.

## ساختار کلی پروژه

```
hesabix-new/
├── hesabixCore/        # Backend — Symfony 7.2 / PHP 8.2+
├── webUI/              # Frontend — Vue 3 / Vuetify 3 / TypeScript
├── public_html/        # Document root (vite build output + index.php)
├── hesabixArchive/     # فایل‌های آرشیو (آواتار، مهر، پشتیبان‌ها)
├── hesabixBackup/      # پشتیبان پایگاه داده
├── docker/             # تنظیمات Docker
├── docker-compose.yml
└── Dockerfile
```

---

## Backend — `hesabixCore/`

### Stack
- **Symfony 7.2** با PHP >= 8.2
- **Doctrine ORM 3.x** — MySQL 8 (پیش‌فرض)
- **mPDF / TCPDF / DomPDF** — تولید PDF
- **PhpSpreadsheet** — Excel import/export
- **Nelmio CORS + API Doc** — مدیریت CORS و Swagger
- **MeliPayamak** — سرویس پیامک

### ساختار `src/`

```
src/
├── Command/            # دستورات CLI Symfony
├── Controller/         # کنترلرهای HTTP (API routes)
├── Entity/             # موجودیت‌های Doctrine ORM
├── Repository/         # مخازن داده Doctrine
├── Service/            # سرویس‌های اصلی
├── Security/           # احراز هویت و مجوز
├── EventListener/      # شنوندگان رویداد
├── Twig/               # افزونه‌های Twig
├── Module/             # ماژول‌های کمکی
└── Kernel.php
```

### Commands (`src/Command/`)
| فایل | کار |
|---|---|
| `ReleaseUpdateLockCommand.php` | باز کردن قفل آپدیت |
| `TestEnvironmentCommand.php` | بررسی محیط اجرا |
| `UpdateSoftwareCommand.php` | آپدیت نرم‌افزار |
| `UpdateSupportTicketsCommand.php` | بروزرسانی تیکت‌های پشتیبانی |

### Controllers (`src/Controller/`)

#### مسیرهای اصلی API
- `/api/acc/*` — عملیات حسابداری کسب‌وکار (نیاز به ROLE_USER)
- `/api/app/*` — عملیات عمومی اپ (نیاز به ROLE_USER)
- `/api/admin/*` — پنل مدیریت (نیاز به ROLE_ADMIN)
- `/hooks/*` — وب‌هوک‌ها (نیاز به ROLE_USER)
- `/doc/api` — Swagger UI

| کنترلر | حوزه |
|---|---|
| `AccountingPackageController` | بسته‌های حسابداری |
| `AdminController` | مدیریت سیستم |
| `ArchiveController` | مدیریت فایل‌های آرشیو |
| `AvatarController` | آواتار کاربران و کسب‌وکار |
| `BankController` | حساب‌های بانکی |
| `BusinessController` | مدیریت کسب‌وکار |
| `BuyController` | فاکتور خرید |
| `CashdeskController` | صندوق |
| `ChequeController` | مدیریت چک |
| `CommodityController` | کالا و خدمات |
| `CostController` | هزینه‌ها |
| `DashboardController` | داشبورد |
| `DirectHesabdariDoc` | ورود مستقیم سند حسابداری |
| `ExploreAccountsController` | گردش حساب |
| `GeneralController` | عمومی |
| `HesabdariController` | حسابداری عمومی |
| `HesabdariDocController` | مدیریت اسناد |
| `HookController` | وب‌هوک‌ها |
| `IncomeController` | درآمدها |
| `InvoiceController` | فاکتور فروش |
| `LogController` | لاگ عملیات |
| `MoadiyanController` | مودیان مالیاتی |
| `MoneyController` | ارز/واحد پول |
| `MostdesController` | توضیحات پرکاربرد |
| `NotesController` | یادداشت‌ها |
| `NotificationsController` | اعلان‌ها |
| `OpenbalanceController` | تراز افتتاحیه |
| `PersonsController` | اشخاص (مشتریان، تأمین‌کنندگان) |
| `PluginController` | مدیریت افزونه‌ها |
| `PreinvoiceController` | پیش‌فاکتور |
| `PrintersController` | چاپگرها و صف چاپ |
| `ProjectController` | پروژه‌ها |
| `ReportController` | گزارش‌ها |
| `RfbuyController` / `RfsellController` | برگشت از خرید/فروش |
| `SalaryController` | حقوق و دستمزد |
| `SellController` | فاکتور فروش |
| `ShareHolderController` | سهامداران |
| `SMSController` | ارسال پیامک |
| `StoreroomController` | انبار |
| `SupportController` | پشتیبانی |
| `TransferController` | انتقال وجه |
| `UserController` | مدیریت کاربران |
| `WalletController` | کیف پول |
| `YearController` | سال مالی |

**Sub-controllers:**
- `Componenets/BankController` — کامپوننت بانک
- `Componenets/CashdeskController` — کامپوننت صندوق
- `Componenets/DocsearchController` — جستجوی اسناد
- `Front/PayController` — درگاه پرداخت
- `Front/ShortlinksController` — لینک کوتاه
- `Front/Store/StoreController` — فروشگاه افزونه
- `Front/UiGeneralController` — UI عمومی
- `Front/UserController` — کاربر (Frontend)
- `Plugins/Hrm/DocsController` — اسناد HRM
- `Plugins/Membership/MembershipController` — عضویت
- `Plugins/PlugGhestaController` — افزونه قسط
- `Plugins/PlugNoghreController` — افزونه نقره
- `Plugins/PlugRepserviceController` — افزونه خدمات پس از فروش
- `System/CronJobController` — زمان‌بندی کرون
- `System/DatabaseController` — مدیریت پایگاه داده
- `System/RegistrySettingsController` — تنظیمات رجیستری
- `System/StatementController` — صورت‌حساب
- `System/UpdateCoreController` — آپدیت هسته

### Entities (`src/Entity/`)

**موجودیت‌های اصلی (Core):**
| موجودیت | توضیح |
|---|---|
| `User` | کاربر (شناسه: mobile) |
| `Business` | کسب‌وکار/شرکت (tenant اصلی) |
| `Year` | سال مالی (هر Business چند Year دارد) |
| `Money` | واحد ارز/پول |
| `Permission` | دسترسی کاربر به کسب‌وکار |

**موجودیت‌های حسابداری:**
| موجودیت | توضیح |
|---|---|
| `HesabdariDoc` | سند حسابداری |
| `HesabdariRow` | ردیف سند حسابداری |
| `HesabdariTable` | جدول حساب (سرفصل) |

**موجودیت‌های مالی:**
| موجودیت | توضیح |
|---|---|
| `BankAccount` | حساب بانکی |
| `Cashdesk` | صندوق |
| `Cheque` | چک |
| `Salary` | حقوق |
| `WalletTransaction` | تراکنش کیف پول |
| `Shareholder` | سهامدار |

**موجودیت‌های کالا و انبار:**
| موجودیت | توضیح |
|---|---|
| `Commodity` | کالا/خدمت |
| `CommodityCat` | دسته‌بندی کالا |
| `CommodityDrop` | ضایعات کالا |
| `CommodityDropLink` | لینک ضایعات کالا |
| `CommodityUnit` | واحد اندازه‌گیری |
| `Storeroom` | انبار |
| `StoreroomItem` | آیتم انبار |
| `StoreroomTicket` | رسید انبار |
| `StoreroomTransferType` | نوع انتقال انبار |
| `PriceList` | لیست قیمت |
| `PriceListDetail` | جزئیات لیست قیمت |

**موجودیت‌های اشخاص:**
| موجودیت | توضیح |
|---|---|
| `Person` | شخص (مشتری/تأمین‌کننده/...) |
| `PersonCard` | کارت حساب شخص |
| `PersonPrelabel` | پیش‌برچسب شخص |
| `PersonType` | نوع شخص |

**موجودیت‌های سیستمی:**
| موجودیت | توضیح |
|---|---|
| `APIToken` | توکن API |
| `UserToken` | توکن کاربر |
| `Settings` | تنظیمات |
| `Registry` | رجیستری سیستم |
| `Log` | لاگ عملیات |
| `Notification` | اعلان |
| `Note` | یادداشت |
| `Hook` | وب‌هوک |
| `ArchiveFile` | فایل آرشیو |
| `ArchiveOrders` | سفارشات آرشیو |
| `EmailHistory` | تاریخچه ایمیل |
| `DashboardSettings` | تنظیمات داشبورد |
| `BackBuiltModule` | ماژول‌های پیش‌ساخته |

**موجودیت‌های چاپ:**
| موجودیت | توضیح |
|---|---|
| `Printer` | چاپگر |
| `PrinterQueue` | صف چاپ |
| `PrintTemplate` | قالب چاپ |
| `PrintItem` | آیتم چاپ |
| `PrintOptions` | گزینه‌های چاپ |

**موجودیت‌های افزونه:**
| موجودیت | توضیح |
|---|---|
| `Plugin` | افزونه |
| `PluginProdect` | محصول افزونه |
| `PlugGhestaDoc` / `PlugGhestaItem` | افزونه اقساط |
| `PlugHrmDoc` / `PlugHrmDocItem` | افزونه HRM |
| `PlugNoghreOrder` | افزونه نقره |
| `PlugRepserviceOrder` / `PlugRepserviceOrderState` | افزونه خدمات پس از فروش |

**موجودیت‌های دیگر:**
| موجودیت | توضیح |
|---|---|
| `PreInvoiceDoc` / `PreInvoiceItem` | پیش‌فاکتور |
| `InvoiceType` | نوع فاکتور |
| `Project` | پروژه |
| `MostDes` | توضیحات پرکاربرد |
| `SMSSettings` / `SMSPays` | تنظیمات پیامک |
| `PayInfoTemp` | اطلاعات موقت پرداخت |
| `AccountingPackageOrder` | سفارش بسته حسابداری |
| `ChangeReport` | گزارش تغییرات |
| `Statment` | صورت‌حساب |
| `Support` | تیکت پشتیبانی |

### Services (`src/Service/`)

| سرویس | نقش |
|---|---|
| `Access` | **مهم‌ترین سرویس** — بررسی دسترسی + resolve کردن Business/Year/Money از HTTP headers |
| `Provider` | کلاس پایه: pagination، search params، Excel export |
| `Log` | ثبت لاگ عملیات کاربران |
| `JsonResp` | ساخت پاسخ‌های JSON استاندارد |
| `Jdate` | تبدیل تاریخ شمسی/میلادی |
| `pdfMGR` | تولید PDF (mPDF/TCPDF/DomPDF) |
| `Printers` | مدیریت صف چاپ |
| `registryMGR` | مدیریت تنظیمات رجیستری سیستم |
| `SMS` | ارسال پیامک (MeliPayamak) |
| `PayMGR` | مدیریت درگاه پرداخت |
| `Notification` | ارسال و مدیریت اعلان‌ها |
| `Explore` | گردش حساب‌های حسابداری |
| `Extractor` | استخراج داده |
| `CaptchaService` | مدیریت کپچا |
| `PluginService` | مدیریت افزونه‌ها |
| `AccountingPermissionService` | سرویس مجوزهای حسابداری |
| `Twig` / `twigFunctions` | توابع Twig |

### Security (`src/Security/`)

| فایل | نقش |
|---|---|
| `AccessDeniedHandler` | پاسخ به دسترسی رد شده |
| `ApiKeyAuthenticator` | احراز هویت از طریق `api-key` header |
| `AuthenticationEntryPoint` | نقطه ورود احراز هویت |
| `AuthenticationFailureHandler` | مدیریت خطای login |
| `BackAuthAuthenticator` | احراز هویت پنل مدیریت |
| `EmailVerifier` | تأیید ایمیل |
| `ParttyAuthenticator` | احراز هویت شخص ثالث |

### HTTP Headers مهم (برای هر request)
- `activeBid` — شناسه کسب‌وکار فعال
- `activeYear` — شناسه سال مالی فعال
- `activeMoney` — نام ارز فعال
- `api-key` — کلید API (برای دسترسی برنامه‌ای)

### دستورات توسعه

```bash
# در پوشه hesabixCore/
composer install
php bin/console doctrine:migrations:migrate
php bin/console cache:clear
php bin/console server:run
```

---

## Frontend — `webUI/`

### Stack
- **Vue 3** + **TypeScript**
- **Vuetify 3** — UI component library
- **Pinia** — state management
- **Vue Router 4** — hash history mode
- **Vite 6** — build tool
- **vue-i18n** — فارسی/انگلیسی
- **Axios** — HTTP client
- **ApexCharts** — نمودار
- **SweetAlert2** — دیالوگ‌ها
- **Jalali/date-fns-jalali** — تاریخ شمسی

### ساختار `src/`

```
src/
├── main.ts                  # نقطه ورود
├── App.vue                  # کامپوننت ریشه
├── hesabixConfig.js         # تنظیمات endpoint های API
├── router/index.ts          # مسیریاب اصلی
├── stores/                  # Pinia stores
│   ├── applicationStore.ts  # state اصلی برنامه
│   ├── userStore.ts         # state کاربر
│   └── counter.ts
├── i18n/                    # ترجمه‌ها
│   ├── fa_lang.ts           # فارسی
│   ├── en_lang.ts           # انگلیسی
│   └── i18n.ts
├── theme/                   # تم‌های روشن/تاریک
├── components/              # کامپوننت‌های مشترک
├── views/                   # صفحات
│   ├── user/                # صفحات کاربری
│   ├── acc/                 # صفحات حسابداری
│   └── wizard/              # راه‌اندازی اولیه
├── scss/                    # استایل‌ها
└── utils/                   # ابزارهای کمکی
```

### Stores
- `applicationStore` — کسب‌وکار فعال، سال مالی، ارز، منو
- `userStore` — اطلاعات کاربر لاگین‌شده، توکن

### Components مشترک (`src/components/`)
- `forms/Haccountsearch.vue` — جستجوی حساب حسابداری
- `forms/Hpersonsearch.vue` — جستجوی شخص
- `forms/Hcommoditysearch.vue` — جستجوی کالا
- `forms/Hdatepicker.vue` — تاریخ‌پیکر شمسی
- `forms/HesabdariTreeView.vue` — نمایش درختی حساب‌ها
- `forms/Hnumberinput.vue` — ورودی عددی با فرمت
- `forms/Hbankaccountsearch.vue` — جستجوی حساب بانکی
- `forms/Hcashdesksearch.vue` — جستجوی صندوق
- `PrintDialog.vue` — دیالوگ چاپ
- `Editor.vue` — ویرایشگر متن (Tiptap)

### Views — صفحات حسابداری (`src/views/acc/`)

| مسیر | صفحه |
|---|---|
| `/` (dashboard) | داشبورد |
| `accounting/` | اسناد حسابداری (list، mod، viewDoc، table، openBalance، closeyear) |
| `bank/` | حساب بانکی (list، mod، card) |
| `buy/` | خرید (list، mod، viewInvoice) |
| `sell/` | فروش (list، mod، viewInvoice، fastMod) |
| `presell/` | پیش‌فاکتور (list، mod، view، viewInvoice) |
| `rfbuy/` / `rfsell/` | برگشت خرید/فروش |
| `cashdesk/` | صندوق (list، mod، card) |
| `cheque/` | چک (list، mod، input، output، transfer) |
| `persons/` | اشخاص (list، insert، card، receive/، send/) |
| `commodity/` | کالا (list، mod، cat/، drop/، priceList/) |
| `storeroom/` | انبار (list، mod، io/، commodityCheck/) |
| `salary/` | حقوق (list، mod، card) |
| `transfer/` | انتقال وجه (list، mod) |
| `costs/` | هزینه (list، mod) |
| `incomes/` | درآمد (list، mod) |
| `reports/` | گزارش‌ها (balanceSheet، persons/، commodity/، explore_accounts) |
| `plugins/` | افزونه‌ها (ghesta/، hrm/، noghre/، repservice/، onlinestore/، ...) |
| `settings/` | تنظیمات (bussiness، avatar، logs، print، user_rolls، user_perm_edit) |
| `store/` | فروشگاه افزونه |
| `wallet/` | کیف پول |
| `smspanel/` | پنل پیامک |
| `printers/` | چاپگرها |
| `notifications/` | اعلان‌ها |
| `archive/` | آرشیو |

### Views — صفحات کاربری (`src/views/user/`)
- `login.vue` — ورود
- `register.vue` — ثبت‌نام
- `profile/` — پروفایل (dashboard، business/، support/، forget-password، ...)
- `manager/` — پنل مدیریت سیستم (business/، users/، settings/، wallet/، ...)

### دستورات frontend

```bash
# در پوشه webUI/
npm install
npm run dev       # سرور توسعه
npm run build     # build تولید (خروجی به public_html)
npm run type-check # بررسی TypeScript
```

---

## Docker

```bash
docker-compose up -d
```

- Apache + PHP 8.2 روی پورت 80
- MySQL 8 روی پورت 3306
- مسیر public: `public_html/`

---

## نکات مهم معماری

1. **Multi-tenant**: هر `Business` یک tenant مستقل است. همه عملیات با `Business` + `Year` + `Money` scope می‌شوند.
2. **Permission**: کلاس `Access` در هر controller inject می‌شود؛ `hasRole()` حتماً قبل از هر عملیات حساس فراخوانی شود.
3. **سند حسابداری**: `HesabdariDoc` + `HesabdariRow` — هر سند n ردیف دارد و باید ترازنامه داشته باشد (بدهکار = بستانکار).
4. **تاریخ**: تمام تاریخ‌ها به فرمت شمسی ذخیره می‌شوند. از `Jdate` service استفاده کنید.
5. **Plugin system**: افزونه‌ها از طریق `Plugin` entity و `PluginService` مدیریت می‌شوند.
6. **API Auth**: هر request باید header های `activeBid` و `activeYear` را داشته باشد.

---

## مخزن دوم: `accounting`

مخزن `hamidrezasoltanian/accounting` در شاخه `claude/code-indexing-NeV79` قرار دارد و در حال حاضر خالی است (بدون کامیت).
