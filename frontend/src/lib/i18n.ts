import i18n from 'i18next'
import { initReactI18next } from 'react-i18next'

/**
 * UI chrome (navigation, common actions, statuses) is translated; the language follows the
 * user's profile locale, falling back to the organization locale. Missing keys fall back to English.
 */
const en = {
  nav: {
    dashboard: 'Dashboard', crm: 'CRM', customers: 'Customers', leads: 'Leads', deals: 'Deals', activities: 'Activities',
    hr: 'HR', hrDashboard: 'HR overview', employees: 'Employees', departments: 'Departments', positions: 'Positions',
    schedules: 'Work schedules', attendance: 'Attendance', monitor: 'Attendance monitor', calendar: 'Attendance calendar',
    penalties: 'Penalties', payroll: 'Payroll', reports: 'Reports', settings: 'Settings', organization: 'Organization',
    users: 'Users', roles: 'Roles & permissions', devices: 'Attendance devices', notifications: 'Notifications',
    audit: 'Audit log', system: 'System health', pipelines: 'Pipelines', fields: 'Tags & custom fields', holidays: 'Holidays',
    platform: 'Platform', organizations: 'Organizations', profile: 'Profile', myAttendance: 'My attendance',
    finance: 'Finance', booking: 'Booking', telephony: 'Telephony', soon: 'Soon',
  },
  common: {
    save: 'Save', saveChanges: 'Save changes', cancel: 'Cancel', delete: 'Delete', edit: 'Edit', create: 'Create', add: 'Add',
    search: 'Search', filters: 'Filters', reset: 'Reset', apply: 'Apply', export: 'Export', close: 'Close', signOut: 'Sign out',
    loading: 'Loading…', noResults: 'Nothing matches these filters.', retry: 'Try again', today: 'Today', thisWeek: 'This week',
    thisMonth: 'This month', thisQuarter: 'This quarter', custom: 'Custom range', all: 'All', status: 'Status', actions: 'Actions',
    lightTheme: 'Light theme', darkTheme: 'Dark theme', language: 'Language', searchEverything: 'Search customers, leads, deals, employees',
  },
  status: {
    present: 'Present', late: 'Late', absent: 'Absent', early_leave: 'Early leave', on_leave: 'On leave', holiday: 'Holiday',
    weekend: 'Weekend', incomplete: 'Incomplete', working: 'Working now', left: 'Left', not_arrived: 'Not arrived yet',
  },
}

const ru: typeof en = {
  nav: {
    dashboard: 'Главная', crm: 'CRM', customers: 'Клиенты', leads: 'Лиды', deals: 'Сделки', activities: 'Задачи и события',
    hr: 'Кадры', hrDashboard: 'Обзор HR', employees: 'Сотрудники', departments: 'Отделы', positions: 'Должности',
    schedules: 'Графики работы', attendance: 'Посещаемость', monitor: 'Монитор посещаемости', calendar: 'Календарь посещаемости',
    penalties: 'Штрафы', payroll: 'Зарплата', reports: 'Отчёты', settings: 'Настройки', organization: 'Организация',
    users: 'Пользователи', roles: 'Роли и права', devices: 'Терминалы учёта', notifications: 'Уведомления',
    audit: 'Журнал аудита', system: 'Состояние системы', pipelines: 'Воронки', fields: 'Теги и поля', holidays: 'Праздники',
    platform: 'Платформа', organizations: 'Организации', profile: 'Профиль', myAttendance: 'Моя посещаемость',
    finance: 'Финансы', booking: 'Бронирование', telephony: 'Телефония', soon: 'Скоро',
  },
  common: {
    save: 'Сохранить', saveChanges: 'Сохранить изменения', cancel: 'Отмена', delete: 'Удалить', edit: 'Изменить', create: 'Создать',
    add: 'Добавить', search: 'Поиск', filters: 'Фильтры', reset: 'Сбросить', apply: 'Применить', export: 'Экспорт', close: 'Закрыть',
    signOut: 'Выйти', loading: 'Загрузка…', noResults: 'Ничего не найдено по этим фильтрам.', retry: 'Повторить', today: 'Сегодня',
    thisWeek: 'Эта неделя', thisMonth: 'Этот месяц', thisQuarter: 'Этот квартал', custom: 'Период', all: 'Все', status: 'Статус',
    actions: 'Действия', lightTheme: 'Светлая тема', darkTheme: 'Тёмная тема', language: 'Язык',
    searchEverything: 'Поиск клиентов, лидов, сделок, сотрудников',
  },
  status: {
    present: 'Присутствует', late: 'Опоздание', absent: 'Отсутствует', early_leave: 'Ранний уход', on_leave: 'В отпуске',
    holiday: 'Праздник', weekend: 'Выходной', incomplete: 'Нет отметки ухода', working: 'На работе', left: 'Ушёл',
    not_arrived: 'Ещё не пришёл',
  },
}

const uz: typeof en = {
  nav: {
    dashboard: 'Bosh sahifa', crm: 'CRM', customers: 'Mijozlar', leads: 'Lidlar', deals: 'Bitimlar', activities: 'Vazifalar',
    hr: 'Kadrlar', hrDashboard: 'HR sharhi', employees: 'Xodimlar', departments: "Bo'limlar", positions: 'Lavozimlar',
    schedules: 'Ish jadvallari', attendance: 'Davomat', monitor: 'Davomat monitori', calendar: 'Davomat kalendari',
    penalties: 'Jarimalar', payroll: 'Ish haqi', reports: 'Hisobotlar', settings: 'Sozlamalar', organization: 'Tashkilot',
    users: 'Foydalanuvchilar', roles: 'Rollar va huquqlar', devices: 'Davomat qurilmalari', notifications: 'Bildirishnomalar',
    audit: 'Audit jurnali', system: 'Tizim holati', pipelines: 'Voronkalar', fields: 'Teglar va maydonlar', holidays: 'Bayramlar',
    platform: 'Platforma', organizations: 'Tashkilotlar', profile: 'Profil', myAttendance: 'Mening davomatim',
    finance: 'Moliya', booking: 'Bron qilish', telephony: 'Telefoniya', soon: 'Tez orada',
  },
  common: {
    save: 'Saqlash', saveChanges: "O'zgarishlarni saqlash", cancel: 'Bekor qilish', delete: "O'chirish", edit: 'Tahrirlash',
    create: 'Yaratish', add: "Qo'shish", search: 'Qidirish', filters: 'Filtrlar', reset: 'Tozalash', apply: "Qo'llash",
    export: 'Eksport', close: 'Yopish', signOut: 'Chiqish', loading: 'Yuklanmoqda…', noResults: 'Bu filtrlar bo‘yicha hech narsa topilmadi.',
    retry: 'Qayta urinish', today: 'Bugun', thisWeek: 'Shu hafta', thisMonth: 'Shu oy', thisQuarter: 'Shu chorak', custom: 'Davr',
    all: 'Hammasi', status: 'Holat', actions: 'Amallar', lightTheme: "Yorug' mavzu", darkTheme: "Qorong'i mavzu", language: 'Til',
    searchEverything: 'Mijoz, lid, bitim, xodimlarni qidirish',
  },
  status: {
    present: 'Kelgan', late: 'Kechikkan', absent: 'Kelmagan', early_leave: 'Erta ketgan', on_leave: "Ta'tilda", holiday: 'Bayram',
    weekend: 'Dam olish', incomplete: 'Ketish belgilanmagan', working: 'Ishda', left: 'Ketgan', not_arrived: 'Hali kelmagan',
  },
}

void i18n.use(initReactI18next).init({
  resources: { en: { translation: en }, ru: { translation: ru }, uz: { translation: uz } },
  lng: (() => {
    try {
      return localStorage.getItem('eversoft.locale') ?? 'en'
    } catch {
      return 'en'
    }
  })(),
  fallbackLng: 'en',
  interpolation: { escapeValue: false },
})

i18n.on('languageChanged', (lng) => {
  try {
    localStorage.setItem('eversoft.locale', lng)
  } catch {
    /* ignore */
  }
  document.documentElement.lang = lng
})

export default i18n
