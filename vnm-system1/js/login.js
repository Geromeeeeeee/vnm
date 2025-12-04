// --- DOM Elements ---
const loginForm = document.getElementById("loginForm");
const signupForm = document.getElementById("signupForm");
const fullname = document.getElementById("fullname");
const email = document.getElementById("email");
const signupPassword = document.getElementById("signupPassword");
const phone = document.getElementById("phone");
const address = document.getElementById("address");
const license = document.getElementById("license");

// --- Form Switching Logic ---
document.getElementById("toSignup").onclick = e => {
  e.preventDefault();
  loginForm.classList.remove("active");
  signupForm.classList.add("active");
};

document.getElementById("toLogin").onclick = e => {
  e.preventDefault();
  signupForm.classList.remove("active");
  loginForm.classList.add("active");
};

// --- Password Toggle ---
function togglePassword(id, icon) {
  const field = document.getElementById(id);
  // Toggle the type attribute between "password" and "text"
  field.type = field.type === "password" ? "text" : "password";
  // Change the icon/text
  icon.textContent = field.type === "text" ? "hide" : "👁";
}

// Attach the global function to the window object so it's accessible from inline HTML onclick
window.togglePassword = togglePassword; 


// --- REAL-TIME VALIDATION FUNCTIONS ---
function setError(field, msg) {
  document.getElementById("err_" + field).textContent = msg;
}

function validateFullname() {
  let v = fullname.value.trim();
  let p = v.split(" ").filter(n => n);
  if (p.length < 2) return setError("fullname", "Enter first & last name");
  if (!/^[A-Za-z ]+$/.test(v)) return setError("fullname", "Letters only");
  if (v.length < 5) return setError("fullname", "Too short");
  if (p[0].toLowerCase() === p[1].toLowerCase()) return setError("fullname", "Names cannot match");
  setError("fullname", "");
}

function validateEmail() {
  let v = email.value.trim();
  // Simplified client-side pattern check
  let pat = /^[^@]+@[^@]+\.[A-Za-z]{2,}$/; 
  if (!pat.test(v)) return setError("email", "Invalid email");
  setError("email", "");
}

function validatePassword() {
  let v = signupPassword.value;
  if (v.length < 8 || v.length > 15) return setError("password", "8–15 characters");
  if (!/[A-Za-z]/.test(v) || !/[0-9]/.test(v)) return setError("password", "Letters + numbers");
  setError("password", "");
}

function validateRequired(id) {
  let el = document.getElementById(id);
  let field = id;
  if (el.value.trim() === "") setError(field, "Required");
  else setError(field, "");
}

// --- Attach Listeners for Real-Time Validation ---
if (fullname) fullname.addEventListener("input", validateFullname);
if (email) email.addEventListener("input", validateEmail);
if (signupPassword) signupPassword.addEventListener("input", validatePassword);

if (phone) phone.addEventListener("input", () => validateRequired("phone"));
if (address) address.addEventListener("input", () => validateRequired("address"));
if (license) license.addEventListener("input", () => validateRequired("license"));

// --- Final Check before Submission ---
function validateSignup() {
  // Run all validation functions one last time
  validateFullname();
  validateEmail();
  validatePassword();
  validateRequired("phone");
  validateRequired("address");
  validateRequired("license");

  // Check if all error messages are empty
  const isValid = [...document.querySelectorAll(".error")].every(e => e.textContent === "");
  return isValid;
}

// Attach the global function to the window object so it's accessible from inline HTML onsubmit
window.validateSignup = validateSignup;