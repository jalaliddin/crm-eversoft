/** Where customers and leads come from. */
export const SOURCES = ['website', 'referral', 'instagram', 'telegram', 'facebook', 'cold_call', 'exhibition', 'other'].map((s) => ({ value: s, label: s.replace('_', ' ').replace(/^\w/, (c) => c.toUpperCase()) }))
