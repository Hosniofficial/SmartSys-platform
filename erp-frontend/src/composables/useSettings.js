// Centralized helpers for Settings view
// Provides dirty-state tracking and snapshot management per section

import { computed, toRaw } from 'vue'

export function useDirtyTracking(sections) {
  // sections is an object like { general: ref({}), invoice: ref({}), printers: ref({}), users?: ref([]) }
  const sectionKeys = Object.keys(sections || {})
  const snapshots = Object.fromEntries(sectionKeys.map(k => [k, null]))

  function clone(obj) {
    try {
      return JSON.parse(JSON.stringify(toRaw(obj)))
    } catch (_) {
      return toRaw(obj)
    }
  }

  function setSnapshotsFromCurrent() {
    for (const key of sectionKeys) {
      if (sections[key]) snapshots[key] = clone(sections[key].value)
    }
  }

  function isSectionDirty(sectionKey) {
    if (!sections[sectionKey]) return false
    if (!snapshots[sectionKey]) return false
    try {
      return JSON.stringify(sections[sectionKey].value) !== JSON.stringify(snapshots[sectionKey])
    } catch (_) {
      return false
    }
  }

  const isDirty = computed(() => sectionKeys.some(k => isSectionDirty(k)))

  function resetSectionToSnapshot(sectionKey) {
    if (!sections[sectionKey] || !snapshots[sectionKey]) return
    sections[sectionKey].value = clone(snapshots[sectionKey])
  }

  return {
    isDirty,
    isSectionDirty,
    setSnapshotsFromCurrent,
    resetSectionToSnapshot,
  }
}
