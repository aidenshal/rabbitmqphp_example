const form = document.querySelector('#registrationForm');
const password = document.querySelector('#password');
const confirmation = document.querySelector('#confirm_password');
const strengthBars = [...document.querySelectorAll('.strength span')];
const strengthText = document.querySelector('#strengthText');
const matchText = document.querySelector('#matchText');
const message = document.querySelector('#formMessage');
const submitButton = document.querySelector('#submitButton');

function passwordScore(value) {
  let score = 0;
  if (value.length >= 12) score += 1;
  if (/[a-z]/.test(value) && /[A-Z]/.test(value)) score += 1;
  if (/\d/.test(value)) score += 1;
  if (/[^A-Za-z0-9]/.test(value)) score += 1;
  return score;
}

function updateStrength() {
  const score = passwordScore(password.value);
  const labels = ['12+ characters', 'Getting started', 'Good', 'Strong', 'Excellent'];
  strengthText.textContent = labels[score];
  strengthBars.forEach((bar, index) => bar.classList.toggle('active', index < score));
  updateMatch();
}

function updateMatch() {
  if (!confirmation.value) {
    matchText.textContent = '';
    confirmation.setCustomValidity('');
    return;
  }
  const matches = password.value === confirmation.value;
  matchText.textContent = matches ? 'Passwords match.' : 'Passwords do not match yet.';
  matchText.className = matches ? 'match-ok' : 'match-error';
  confirmation.setCustomValidity(matches ? '' : 'Passwords do not match.');
}

document.querySelectorAll('[data-toggle-password]').forEach((button) => {
  button.addEventListener('click', () => {
    const input = document.getElementById(button.dataset.togglePassword);
    const revealing = input.type === 'password';
    input.type = revealing ? 'text' : 'password';
    button.textContent = revealing ? 'Hide' : 'Show';
    button.setAttribute('aria-label', revealing ? 'Hide password' : 'Show password');
  });
});

password.addEventListener('input', updateStrength);
confirmation.addEventListener('input', updateMatch);

function showMessage(text, kind) {
  message.textContent = text;
  message.className = `message ${kind}`;
  message.hidden = false;
  message.focus({ preventScroll: true });
}

form.addEventListener('submit', async (event) => {
  event.preventDefault();
  updateMatch();
  if (!form.reportValidity()) return;

  message.hidden = true;
  submitButton.disabled = true;
  submitButton.setAttribute('aria-busy', 'true');
  submitButton.querySelector('span').textContent = 'Creating account…';

  try {
    const response = await fetch(form.action, {
      method: 'POST',
      headers: { Accept: 'application/json' },
      body: new FormData(form),
    });
    const result = await response.json();
    if (!response.ok || result.ok !== true) {
      throw new Error(result.message || 'The account could not be created.');
    }
    showMessage(result.message, 'success');
    form.reset();
    updateStrength();
    window.setTimeout(() => { window.location.href = 'login.php?registered=1'; }, 900);
  } catch (error) {
    showMessage(error.message || 'Registration is unavailable. Please try again.', 'error');
  } finally {
    submitButton.disabled = false;
    submitButton.removeAttribute('aria-busy');
    submitButton.querySelector('span').textContent = 'Create my account';
  }
});
