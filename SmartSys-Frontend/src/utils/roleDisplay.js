export function getRoleCode(role) {
  if (!role) return ''
  return String(role.role_code || role.code || role.name || role.role || '').trim()
}

export function getRoleDisplayName(role) {
  if (!role) return ''
  return String(role.display_name || role.role_display_name || role.name || role.role || role.role_code || '').trim()
}
