/** One color per status across badges, boards and calendars. */
export const COLORS: Record<string, string> = {
  // attendance
  present: 'teal', late: 'saffron', absent: 'red', early_leave: 'orange', on_leave: 'indigo', holiday: 'cyan',
  weekend: 'gray', incomplete: 'grape', working: 'khiva', left: 'gray', not_arrived: 'gray',
  // generic
  active: 'teal', inactive: 'gray', blocked: 'red', terminated: 'red', suspended: 'red', trial: 'cyan',
  // crm
  new: 'blue', contacted: 'cyan', qualified: 'khiva', proposal: 'violet', negotiation: 'orange', converted: 'teal', lost: 'red',
  open: 'blue', won: 'teal',
  pending: 'saffron', in_progress: 'blue', completed: 'teal', cancelled: 'gray',
  low: 'gray', normal: 'blue', high: 'orange', urgent: 'red',
  // payroll / penalties
  approved: 'teal', rejected: 'red', draft: 'gray', calculated: 'blue', paid: 'teal', closed: 'gray',
  // devices / raw events
  ok: 'teal', failed: 'red', processed: 'teal', unmatched: 'saffron', ignored: 'gray',
}

export const statusColor = (status?: string | null) => (status ? (COLORS[status] ?? 'gray') : 'gray')
