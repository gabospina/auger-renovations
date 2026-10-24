// assets/js/quote-voice.js

let recognition = null;
let isRecording = false;

function initVoiceRecognition() {
  const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
  if (!SpeechRecognition) {
    const statusEl = document.getElementById('voice-status');
    if (statusEl) statusEl.textContent = "La reconnaissance vocale n'est pas supportée par ce navigateur (utilisez Chrome).";
    const btn = document.getElementById('btn-voice-rec');
    if (btn) btn.style.display = 'none';
    return;
  }

  recognition = new SpeechRecognition();
  recognition.lang = 'fr-CA'; // French (Canada)
  recognition.interimResults = false;
  recognition.maxAlternatives = 1;

  recognition.onstart = function() {
    isRecording = true;
    const btn = document.getElementById('btn-voice-rec');
    if (btn) {
      btn.style.background = '#dc2626'; // Red recording state
      btn.style.animation = 'pulse 1.5s infinite';
    }
    const statusEl = document.getElementById('voice-status');
    if (statusEl) statusEl.textContent = "Écoute en cours... Parlez clairement.";
  };

  recognition.onresult = function(event) {
    const transcript = event.results[0][0].transcript;
    const statusEl = document.getElementById('voice-status');
    if (statusEl) statusEl.textContent = `Dernier texte capté : "${transcript}"`;
    
    // Process the spoken command
    parseVoiceCommand(transcript);
  };

  recognition.onerror = function(event) {
    console.error("Erreur vocale:", event.error);
    stopVoiceRecording();
  };

  recognition.onend = function() {
    stopVoiceRecording();
  };
}

function toggleVoiceRecording() {
  if (!recognition) initVoiceRecognition();
  if (!recognition) return;

  if (isRecording) {
    recognition.stop();
  } else {
    try {
      recognition.start();
    } catch (e) {
      console.error(e);
    }
  }
}

function stopVoiceRecording() {
  isRecording = false;
  const btn = document.getElementById('btn-voice-rec');
  if (btn) {
    btn.style.background = '#ea580c';
    btn.style.animation = 'none';
  }
}

// Intelligent Parser for Simon's Voice Commands
function parseVoiceCommand(text) {
  const lower = text.toLowerCase();

  // 1. Extract Client Name (e.g. "client Jean Tremblay" or "pour Paul")
  const clientMatch = lower.match(/(?:client|pour|nom)\s+([a-zà-ÿ\s]+)(?:\.|,|\s+salle|\s+dimension|\s+avec|$)/i);
  if (clientMatch && clientMatch[1]) {
    const rawName = clientMatch[1].trim();
    // Capitalize words
    const formattedName = rawName.split(' ').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' ');
    const clientField = document.getElementById('client-name');
    if (clientField) clientField.value = formattedName;
  }

  // 2. Extract Dimensions (e.g. "8 par 10" or "8x10")
  const dimMatch = lower.match(/(\d+)\s*(?:par|x)\s*(\d+)/i);
  if (dimMatch) {
    const length = dimMatch[1];
    const width = dimMatch[2];
    
    // If you have separate length/width inputs in your form:
    const lengthField = document.getElementById('room-length') || document.getElementById('bath-length');
    const widthField = document.getElementById('room-width') || document.getElementById('bath-width');
    
    if (lengthField) lengthField.value = length;
    if (widthField) widthField.value = width;

    // Calculate total sqft if a main sqft input exists
    const sqftField = document.getElementById('bath-size');
    if (sqftField) {
      sqftField.value = parseInt(length) * parseInt(width);
    }
  }

  // 3. Toggle Checkboxes based on spoken keywords
  const keywords = {
    'douche': ['douche', 'bain douche'],
    'plancher': ['plancher', 'céramique sol', 'tuile sol'],
    'vanite': ['vanité', 'lavabo', 'meuble'],
    'toilette': ['toilette', 'bain-toilette'],
    'peinture': ['peinture', 'peindre'],
    'chauffe': ['chauffant', 'plancher chauffant']
  };

  // Loop and trigger corresponding checkboxes if found in speech
  for (const [key, words] of Object.entries(keywords)) {
    const found = words.some(w => lower.includes(w));
    // Find matching checkbox in the DOM
    const checkbox = document.querySelector(`input[data-feature="${key}"], input[name*="${key}"]`);
    if (checkbox) {
      checkbox.checked = found;
    }
  }

  // Automatically trigger recalculation or prefill if available
  if (typeof prefillFromClientRequest === 'function') {
    prefillFromClientRequest();
  } else if (typeof updateCalculations === 'function') {
    updateCalculations();
  }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
  initVoiceRecognition();
});