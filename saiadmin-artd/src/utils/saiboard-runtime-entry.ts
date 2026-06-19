const normalizeSaiboardRuntimeEntry = () => {
  const { pathname, search, hash } = window.location
  if (hash || !pathname.startsWith('/screen/')) return

  const code = pathname.slice('/screen/'.length)
  if (!code) return

  window.location.replace(`/#/screen/${code}${search}`)
}

normalizeSaiboardRuntimeEntry()

export {}
