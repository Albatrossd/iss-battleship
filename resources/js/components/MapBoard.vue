<template>
  <div>
    <div ref="mapEl" style="height: 70vh; border:1px solid #ddd; border-radius:12px;"></div>

    <div style="margin-top: 10px; font-family: system-ui;">
      <div><strong>Round:</strong> {{ roundId ?? '—' }}</div>

<div v-if="hoverCell">
  Hover: {{ cellLabel(hoverCell.row, hoverCell.col) }}
</div>

<div v-if="lastBet">
  <strong>Last bet:</strong> {{ cellLabel(lastBet.row, lastBet.col) }}
</div>

<div v-if="issCell">
  ISS now: row {{ issCell.row }}, col {{ issCell.col }}
</div>

<div v-if="lastScore">
  <strong>Score:</strong> {{ lastScore.score }} ({{ lastScore.distanceCells }} cells away)
</div>
<div v-if="isScoring">
  <em>{{ scoringMessage }}</em>
</div>
      <div v-if="error" style="color:#b00020;">{{ error }}</div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, onBeforeUnmount, ref } from 'vue'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'

const mapEl = ref(null)
const map = ref(null)
const error = ref('')
const roundId = ref(null)
let selectedRect = null
let gridLayer = null
const lastBet = ref(null)
const hoverCell = ref(null)
let issMarker = null

const ROWS = 18
const COLS = 36
const latStep = 180 / ROWS
const lonStep = 360 / COLS

const isScoring = ref(false)
const scoringMessage = ref('')
const issCell = ref(null)
const lastScore = ref(null)



function clamp(n, min, max) {
  return Math.max(min, Math.min(max, n))
}
function rowLabel(row) {
  return String.fromCharCode('A'.charCodeAt(0) + row)
}

function cellLabel(row, col) {
  return `${rowLabel(row)}${col + 1}`
}
function latLonToCell(lat, lon) {
  let row = Math.floor((lat + 90) / latStep)
  let col = Math.floor((lon + 180) / lonStep)

  row = clamp(row, 0, ROWS - 1)
  col = clamp(col, 0, COLS - 1)

  return { row, col }
}
async function fetchJson(url, options = {}) {
  const res = await fetch(url, {
    headers: { Accept: 'application/json', ...(options.headers || {}) },
    ...options,
  })
  const ct = res.headers.get('content-type') || ''
  const data = ct.includes('application/json') ? await res.json() : await res.text()
  if (!res.ok) throw new Error(typeof data === 'string' ? data : (data?.message || JSON.stringify(data)))
  return data
}

async function loadRound() {
  const r = await fetchJson('/api/rounds/current')
  roundId.value = r.id
}
function gridDistance(aRow, aCol, bRow, bCol) {
  // Manhattan distance feels Battleship-y
  return Math.abs(aRow - bRow) + Math.abs(aCol - bCol)
}

function scoreFromCellDistance(d) {
  // Simple “rings”
  if (d === 0) return 100
  if (d <= 1) return 75
  if (d <= 3) return 50
  if (d <= 6) return 25
  return 10
}

let dotInterval

function startDots() {
  let dots = ''
  dotInterval = setInterval(() => {
    dots = dots.length < 3 ? dots + '.' : ''
    scoringMessage.value = `Tracking ISS for score${dots}`
  }, 500)
}

function stopDots() {
  clearInterval(dotInterval)
}
    async function fetchIssWithTimeout(timeoutMs = 20000) {
      const controller = new AbortController()
      const timeout = setTimeout(() => controller.abort(), timeoutMs)

      try {
        const res = await fetch('/api/iss/now', {
          signal: controller.signal,
          headers: { Accept: 'application/json' },
        })

        if (!res.ok) throw new Error('ISS fetch failed')

        return await res.json()
      } finally {
        clearTimeout(timeout)
      }
}
function setIssMarker(lat, lon) {
  if (!map.value) return

  if (issMarker) issMarker.remove()

issMarker = L.circleMarker([lat, lon], {
  radius: 8,
  weight: 2,
  fillOpacity: 0.4,
}).addTo(map.value)

const el = issMarker.getElement()
if (el) {
  el.classList.add('pulsating-marker')

  setTimeout(() => {
    el.classList.remove('pulsating-marker')
    el.classList.add('settled-marker')
  }, 4000) // 4 second pulse
}

}
async function placeBet(row, col) {
  if (isScoring.value) return

  error.value = ''

  try {
    // 1) Save the bet
    const response = await fetch(`/api/rounds/${roundId.value}/bets`, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ row, col }),
    })

    if (!response.ok) {
      const msg = await response.text().catch(() => '')
      throw new Error(msg || 'Bet failed')
    }

    // 2) Update UI immediately
    lastBet.value = { row, col }
    highlightCell(row, col)

    // 3) Start ISS scoring
    isScoring.value = true
    startDots()

    try {
      const iss = await fetchIssWithTimeout(20000)
      setIssMarker(iss.lat, iss.lon)
      const issRC = latLonToCell(iss.lat, iss.lon)

      issCell.value = issRC

      const d = gridDistance(row, col, issRC.row, issRC.col)
      lastScore.value = { distanceCells: d, score: scoreFromCellDistance(d) }

      // Clear loading message once we have a score
      scoringMessage.value = ''
    } catch (err) {
      scoringMessage.value = 'ISS tracking timed out. Try again.'
    } finally {
      isScoring.value = false
      stopDots()
    }
  } catch (err) {
    console.error(err)
    error.value = err?.message ?? String(err)
  }
}

onMounted(async () => {
  await loadRound()

  map.value = L.map(mapEl.value, {
    worldCopyJump: true,
    minZoom: 2,
  }).setView([20, 0], 2)

  // Free tiles (no key). Fine for dev/MVP.
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 6,
    attribution: '&copy; OpenStreetMap contributors',
}).addTo(map.value)

drawGrid()

map.value.on('click', (e) => {
  const { row, col } = latLonToCell(e.latlng.lat, e.latlng.lng)
  placeBet(row, col)
})

map.value.on('mousemove', (e) => {
  hoverCell.value = latLonToCell(e.latlng.lat, e.latlng.lng)
})
})

onBeforeUnmount(() => {
  if (map.value) map.value.remove()
  if (issMarker) issMarker.remove()
})


function cellBounds(row, col) {
  const minLat = -90 + row * latStep
  const maxLat = minLat + latStep
  const minLon = -180 + col * lonStep
  const maxLon = minLon + lonStep
  return [[minLat, minLon], [maxLat, maxLon]]
}
function drawGrid() {
  if (!map.value) return

  // Clear previous grid if hot reload runs
  if (gridLayer) gridLayer.remove()

  gridLayer = L.layerGroup()

  for (let r = 0; r < ROWS; r++) {
    for (let c = 0; c < COLS; c++) {
      const bounds = cellBounds(r, c)

      L.rectangle(bounds, {
        weight: 1,
        color: '#000',
        opacity: 0.15,
        fill: false,
        interactive: false, // don't steal mouse events
      }).addTo(gridLayer)
    }
  }

  gridLayer.addTo(map.value)
}
function highlightCell(row, col) {
  if (selectedRect) selectedRect.remove()

  selectedRect = L.rectangle(cellBounds(row, col), {
    weight: 2,
    color: '#000',
    opacity: 0.8,
    fill: false,
  }).addTo(map.value)
}
</script>
