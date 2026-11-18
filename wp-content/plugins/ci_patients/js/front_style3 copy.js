class OncoSureForm {
  constructor() {
    this.currentStep = 1;
    this.tooltipStep = null;
    this.buttonTooltip = null;
    this.fieldErrors = {};
    this.formData = {
      selectedTests: [],
      rushProcessing: false,
      specialInstructions: '',
      firstName: '',
      lastName: '',
      dateOfBirth: '',
      gender: '',
      phoneNumber: '',
      emailAddress: '',
      address: '',
      city: '',
      state: '',
      zipCode: '',
      allAgreements: false,
      paymentMethod: '',
      cardNumber: '',
      expiryDate: '',
      cvv: '',
      cardholderName: '',
      billingAddress: '',
      billingCity: '',
      billingState: '',
      billingZip: '',
      sameAsPatient: true
    };

    this.steps = [
      { number: 1, title: 'Test Selection', icon: 'computer' },
      { number: 2, title: 'Patient Info', icon: 'user' },
      { number: 3, title: 'Agreements', icon: 'document' },
      { number: 4, title: 'Payment', icon: 'card' },
      { number: 5, title: 'Your Order', icon: 'check' }
    ];

    this.testOptions = [
      {
        id: 'comprehensive',
        name: 'Comprehensive Cancer Screening',
        description: 'Full spectrum cancer detection across multiple organ systems using advanced OncoSure technology',
        originalPrice: 3200,
        salePrice: 2500,
        duration: '5-7 business days',
        badge: 'Most Popular'
      },
      {
        id: 'monitoring',
        name: 'Cancer Monitoring Panel',
        description: 'Ongoing monitoring for patients with cancer history or high-risk profiles',
        originalPrice: 1200,
        salePrice: 800,
        duration: '2-3 business days'
      }
    ];

    this.init();
  }

  init() {
    this.renderForm();
    this.attachEventListeners();
  }

  renderForm() {
    const container = document.querySelector('.oncosure-form-container');
    if (!container) return;

    container.innerHTML = `
      <div class="oncosure-main-wrapper">
        <div class="oncosure-form-section">
          <div class="oncosure-header">
            <h1>Order Your Test Kit</h1>
            <p>Complete the steps below to order your cancer screening test</p>
          </div>

          <!-- Step Indicators -->
          <div class="oncosure-step-indicators">
            <div class="oncosure-steps-wrapper">
              ${this.steps.map(step => `
                <div class="oncosure-step ${this.currentStep === step.number ? 'active' : ''}" 
                     onclick="oncoSureForm.handleStepClick(${step.number})">
                  <div class="oncosure-step-content">
                    <div class="oncosure-step-number-row">
                      <div class="oncosure-step-number">${step.number}</div>
                      <div class="oncosure-step-icon">${this.getStepIcon(step.number)}</div>
                    </div>
                    <span class="oncosure-step-title">${step.title}</span>
                  </div>
                  <div class="oncosure-tooltip" id="tooltip-${step.number}" style="display: none;"></div>
                </div>
              `).join('')}
            </div>
          </div>

          <!-- Step Content -->
          <div class="oncosure-step-content-area">
            ${this.renderStepContent()}
          </div>
        </div>

        <!-- Order Summary Sidebar -->
        <div class="oncosure-sidebar">
          <div class="oncosure-order-summary">
            <div class="oncosure-summary-header">
              <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
              </svg>
              <h3>Order Summary</h3>
            </div>
            <div class="oncosure-summary-content">
              ${this.renderOrderSummary()}
            </div>
          </div>
        </div>
      </div>
    `;

    // Set form values after render
    this.setFormValues();
  }

  setFormValues() {
    // Set all form field values based on current formData
    Object.keys(this.formData).forEach(key => {
      const element = document.querySelector(`[data-field="${key}"]`);
      if (element) {
        if (element.type === 'checkbox') {
          element.checked = this.formData[key];
        } else {
          element.value = this.formData[key];
        }
      }
    });
  }

  getStepIcon(stepNumber) {
    const icons = {
      1: `<svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
           <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
           </svg>`,
      2: `<svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
           <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
           </svg>`,
      3: `<svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
           <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 2a1 1 0 000 2h6a1 1 0 100-2H9z" />
           <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4a2 2 0 012-2h12a2 2 0 012 2v16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" />
           <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 8h8M8 12h8M8 16h4" />
           </svg>`,
      4: `<svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
           <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
           </svg>`,
      5: `<svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
           <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
           </svg>`
    };
    return icons[stepNumber] || '';
  }

  renderStepContent() {
    switch (this.currentStep) {
      case 1: return this.renderStep1();
      case 2: return this.renderStep2();
      case 3: return this.renderStep3();
      case 4: return this.renderStep4();
      case 5: return this.renderStep5();
      default: return '';
    }
  }

  renderStep1() {
    return `
      <div class="oncosure-step-section">
        <div class="oncosure-step-header">
          <div class="oncosure-step-icon-circle">
            <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
            </svg>
          </div>
          <div class="oncosure-step-title-section">
            <h3>Choose Your Tests</h3>
            <p>Select the OncoSure screening tests that are right for you</p>
          </div>
        </div>

        <div class="oncosure-tests-grid">
          ${this.testOptions.map(test => `
            <div class="oncosure-test-card ${this.formData.selectedTests.includes(test.id) ? 'selected' : ''}" 
                 onclick="oncoSureForm.handleTestSelection('${test.id}')">
              <div class="oncosure-test-content">
                <div class="oncosure-test-header">
                  <h4>${test.name}</h4>
                  ${test.badge ? `<span class="oncosure-badge">${test.badge}</span>` : ''}
                </div>
                <p class="oncosure-test-description">${test.description}</p>
              </div>
              <div class="oncosure-test-pricing">
                <div class="oncosure-price-row">
                  <span class="oncosure-original-price">$${test.originalPrice.toLocaleString()}</span>
                  <span class="oncosure-sale-price">$${test.salePrice.toLocaleString()}</span>
                </div>
                <p class="oncosure-duration">${test.duration}</p>
              </div>
            </div>
          `).join('')}
        </div>

        <div class="oncosure-rush-processing">
          <input type="checkbox" id="rushProcessing" data-field="rushProcessing" 
                 ${this.formData.rushProcessing ? 'checked' : ''} 
                 onchange="oncoSureForm.updateFormData('rushProcessing', this.checked)">
          <label for="rushProcessing">Rush Processing (+$500) - Get results in 24-48 hours</label>
        </div>

        <div class="oncosure-special-instructions">
          <textarea placeholder="Special Instructions (Optional) - Any special requests or medical history we should know about..." 
                    data-field="specialInstructions"
                    onchange="oncoSureForm.updateFormData('specialInstructions', this.value)">${this.formData.specialInstructions}</textarea>
        </div>

        <div class="oncosure-step-navigation">
          <div></div>
          <button class="oncosure-btn oncosure-btn-primary ${this.formData.selectedTests.length === 0 ? 'disabled' : ''}" 
                  onclick="oncoSureForm.validateStep1WithTooltip()">
            Continue to Patient Info
          </button>
          <div class="oncosure-button-tooltip" id="button-tooltip" style="display: none;"></div>
        </div>
      </div>
    `;
  }

  renderStep2() {
    return `
      <div class="oncosure-step-section">
        <div class="oncosure-step-header">
          <div class="oncosure-step-icon-circle">
            <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
            </svg>
          </div>
          <div class="oncosure-step-title-section">
            <h3>Patient Information</h3>
            <p>We need your details for the test kit and results</p>
          </div>
        </div>

        <div class="oncosure-form-grid">
          <div class="oncosure-form-group">
            <label>First Name *</label>
            <input type="text" placeholder="Enter your first name" data-field="firstName"
                   value="${this.formData.firstName}"
                   onchange="oncoSureForm.updateFormData('firstName', this.value)">
          </div>
          <div class="oncosure-form-group">
            <label>Last Name *</label>
            <input type="text" placeholder="Enter your last name" data-field="lastName"
                   value="${this.formData.lastName}"
                   onchange="oncoSureForm.updateFormData('lastName', this.value)">
          </div>
          <div class="oncosure-form-group">
            <label>Date of Birth *</label>
            <input type="date" data-field="dateOfBirth"
                   value="${this.formData.dateOfBirth}"
                   onchange="oncoSureForm.updateFormData('dateOfBirth', this.value)">
          </div>
          <div class="oncosure-form-group">
            <label>Gender *</label>
            <select data-field="gender" onchange="oncoSureForm.updateFormData('gender', this.value)">
              <option value="">Select gender</option>
              <option value="male" ${this.formData.gender === 'male' ? 'selected' : ''}>Male</option>
              <option value="female" ${this.formData.gender === 'female' ? 'selected' : ''}>Female</option>
              <option value="other" ${this.formData.gender === 'other' ? 'selected' : ''}>Other</option>
              <option value="prefer-not-to-say" ${this.formData.gender === 'prefer-not-to-say' ? 'selected' : ''}>Prefer not to say</option>
            </select>
          </div>
          <div class="oncosure-form-group">
            <label>Phone Number *</label>
            <input type="tel" placeholder="(555) 123-4567" data-field="phoneNumber"
                   value="${this.formData.phoneNumber}"
                   onchange="oncoSureForm.updateFormData('phoneNumber', this.value)">
          </div>
          <div class="oncosure-form-group">
            <label>Email Address *</label>
            <input type="email" placeholder="you@example.com" data-field="emailAddress"
                   value="${this.formData.emailAddress}"
                   onchange="oncoSureForm.updateFormData('emailAddress', this.value)">
          </div>
          <div class="oncosure-form-group">
            <label>Address *</label>
            <input type="text" placeholder="123 Main Street" data-field="address"
                   value="${this.formData.address}"
                   onchange="oncoSureForm.updateFormData('address', this.value)">
          </div>
          <div class="oncosure-form-group">
            <label>City *</label>
            <input type="text" placeholder="Enter city" data-field="city"
                   value="${this.formData.city}"
                   onchange="oncoSureForm.updateFormData('city', this.value)">
          </div>
          <div class="oncosure-form-group">
            <label>State *</label>
            <select data-field="state" onchange="oncoSureForm.updateFormData('state', this.value)">
              <option value="">Select state</option>
              <option value="AL" ${this.formData.state === 'AL' ? 'selected' : ''}>Alabama</option>
              <option value="CA" ${this.formData.state === 'CA' ? 'selected' : ''}>California</option>
              <option value="FL" ${this.formData.state === 'FL' ? 'selected' : ''}>Florida</option>
              <option value="NY" ${this.formData.state === 'NY' ? 'selected' : ''}>New York</option>
              <option value="TX" ${this.formData.state === 'TX' ? 'selected' : ''}>Texas</option>
            </select>
          </div>
          <div class="oncosure-form-group">
            <label>ZIP Code *</label>
            <input type="text" placeholder="12345" data-field="zipCode"
                   value="${this.formData.zipCode}"
                   onchange="oncoSureForm.updateFormData('zipCode', this.value)">
          </div>
        </div>

        <div class="oncosure-step-navigation">
          <button class="oncosure-btn oncosure-btn-secondary" onclick="oncoSureForm.prevStep()">Back</button>
          <button class="oncosure-btn oncosure-btn-primary" onclick="oncoSureForm.validateStep2WithTooltip()">
            Continue to Agreements
          </button>
          <div class="oncosure-button-tooltip" id="button-tooltip" style="display: none;"></div>
        </div>
      </div>
    `;
  }

  renderStep3() {
    return `
      <div class="oncosure-step-section">
        <div class="oncosure-step-header">
          <div class="oncosure-step-icon-circle">
            <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 2a1 1 0 000 2h6a1 1 0 100-2H9z" />
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4a2 2 0 012-2h12a2 2 0 012 2v16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" />
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 8h8M8 12h8M8 16h4" />
            </svg>
          </div>
          <div class="oncosure-step-title-section">
            <h3>Agreements & Consent</h3>
            <p>Please review and accept all agreements to continue</p>
          </div>
        </div>

        <div class="oncosure-agreements-section">
          <h4>By checking the box below, I acknowledge that I have read, understood, and agree to:</h4>
          
          <div class="oncosure-agreement-list">
            <div class="oncosure-agreement-item">
              <span class="oncosure-agreement-number">1.</span>
              <div class="oncosure-agreement-content">
                <strong>Patient Disclaimer:</strong> The terms and conditions outlined in the 
                <a href="#" class="oncosure-link">Patient Disclaimer</a> 
                regarding the testing services and limitations.
              </div>
            </div>
            
            <div class="oncosure-agreement-item">
              <span class="oncosure-agreement-number">2.</span>
              <div class="oncosure-agreement-content">
                <strong>Privacy Policy:</strong> The 
                <a href="#" class="oncosure-link">Privacy Policy</a> 
                regarding the collection, use, and protection of my personal information.
              </div>
            </div>
            
            <div class="oncosure-agreement-item">
              <span class="oncosure-agreement-number">3.</span>
              <div class="oncosure-agreement-content">
                <strong>HIPAA Policy:</strong> The 
                <a href="#" class="oncosure-link">HIPAA Policy</a> 
                regarding the privacy and security of my health information.
              </div>
            </div>
            
            <div class="oncosure-agreement-item">
              <span class="oncosure-agreement-number">4.</span>
              <div class="oncosure-agreement-content">
                <strong>SMS Communications:</strong> I consent to receive SMS Notifications, Alerts & Occasional Communication from OncoSure Testing. Message frequency varies. Messaging & Data rates may apply. You can reply STOP to unsubscribe at any time.
              </div>
            </div>
          </div>
        </div>

        <div class="oncosure-final-agreement">
          <input type="checkbox" id="allAgreements" data-field="allAgreements"
                 ${this.formData.allAgreements ? 'checked' : ''}
                 onchange="oncoSureForm.updateFormData('allAgreements', this.checked)">
          <label for="allAgreements">I have read and agree to all the above agreements and policies *</label>
        </div>

        <div class="oncosure-step-navigation">
          <button class="oncosure-btn oncosure-btn-secondary" onclick="oncoSureForm.prevStep()">Back</button>
          <button class="oncosure-btn oncosure-btn-primary ${!this.formData.allAgreements ? 'disabled' : ''}" 
                  onclick="oncoSureForm.validateStep3WithTooltip()">
            Continue to Payment
          </button>
          <div class="oncosure-button-tooltip" id="button-tooltip" style="display: none;"></div>
        </div>
      </div>
    `;
  }

  renderStep4() {
    return `
      <div class="oncosure-step-section">
        <div class="oncosure-step-header">
          <div class="oncosure-step-icon-circle">
            <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
            </svg>
          </div>
          <div class="oncosure-step-title-section">
            <h3>Payment</h3>
            <p>Enter your payment details and submit your order</p>
          </div>
        </div>

        <div class="oncosure-payment-section">
          <h4>Payment Method *</h4>
          
          <div class="oncosure-payment-methods">
            <div class="oncosure-payment-option ${this.formData.paymentMethod === 'credit_card' ? 'selected' : ''}" 
                 onclick="oncoSureForm.updateFormData('paymentMethod', 'credit_card')">
              <input type="radio" name="paymentMethod" value="credit_card" data-field="paymentMethod"
                     ${this.formData.paymentMethod === 'credit_card' ? 'checked' : ''}>
              <label>
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                </svg>
                Credit / Debit Card
              </label>
            </div>
            
            <div class="oncosure-payment-option ${this.formData.paymentMethod === 'affirm' ? 'selected' : ''}" 
                 onclick="oncoSureForm.updateFormData('paymentMethod', 'affirm')">
              <input type="radio" name="paymentMethod" value="affirm" data-field="paymentMethod"
                     ${this.formData.paymentMethod === 'affirm' ? 'checked' : ''}>
              <label>
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1" />
                </svg>
                Affirm - Pay over time
                <span class="oncosure-payment-note">(Split into payments)</span>
              </label>
            </div>
          </div>

          ${this.formData.paymentMethod === 'credit_card' ? `
            <div class="oncosure-card-details">
              <h4>Card Details</h4>
              <div class="oncosure-form-grid">
                <div class="oncosure-form-group full-width">
                  <label>Cardholder Name *</label>
                  <input type="text" placeholder="Full name on card" data-field="cardholderName"
                         value="${this.formData.cardholderName}"
                         onchange="oncoSureForm.updateFormData('cardholderName', this.value)">
                </div>
                <div class="oncosure-form-group full-width">
                  <label>Card Number *</label>
                  <input type="text" placeholder="1234 5678 9012 3456" maxlength="19" data-field="cardNumber"
                         value="${this.formData.cardNumber}"
                         onchange="oncoSureForm.updateFormData('cardNumber', this.value)">
                </div>
                <div class="oncosure-form-group">
                  <label>Expiry Date *</label>
                  <input type="text" placeholder="MM/YY" maxlength="5" data-field="expiryDate"
                         value="${this.formData.expiryDate}"
                         onchange="oncoSureForm.updateFormData('expiryDate', this.value)">
                </div>
                <div class="oncosure-form-group">
                  <label>CVV *</label>
                  <input type="text" placeholder="123" maxlength="4" data-field="cvv"
                         value="${this.formData.cvv}"
                         onchange="oncoSureForm.updateFormData('cvv', this.value)">
                </div>
              </div>
            </div>
          ` : ''}

          <div class="oncosure-billing-section">
            <h4>Billing Address</h4>
            <div class="oncosure-same-address">
              <input type="checkbox" id="sameAsPatient" data-field="sameAsPatient"
                     ${this.formData.sameAsPatient ? 'checked' : ''} 
                     onchange="oncoSureForm.updateFormData('sameAsPatient', this.checked)">
              <label for="sameAsPatient">Same as patient information</label>
            </div>
            
            ${!this.formData.sameAsPatient ? `
              <div class="oncosure-form-grid">
                <div class="oncosure-form-group full-width">
                  <label>Billing Address *</label>
                  <input type="text" placeholder="123 Main Street" data-field="billingAddress"
                         value="${this.formData.billingAddress}"
                         onchange="oncoSureForm.updateFormData('billingAddress', this.value)">
                </div>
                <div class="oncosure-form-group">
                  <label>City *</label>
                  <input type="text" placeholder="Enter city" data-field="billingCity"
                         value="${this.formData.billingCity}"
                         onchange="oncoSureForm.updateFormData('billingCity', this.value)">
                </div>
                <div class="oncosure-form-group">
                  <label>State *</label>
                  <select data-field="billingState" onchange="oncoSureForm.updateFormData('billingState', this.value)">
                    <option value="">Select state</option>
                    <option value="AL" ${this.formData.billingState === 'AL' ? 'selected' : ''}>Alabama</option>
                    <option value="CA" ${this.formData.billingState === 'CA' ? 'selected' : ''}>California</option>
                    <option value="FL" ${this.formData.billingState === 'FL' ? 'selected' : ''}>Florida</option>
                    <option value="NY" ${this.formData.billingState === 'NY' ? 'selected' : ''}>New York</option>
                    <option value="TX" ${this.formData.billingState === 'TX' ? 'selected' : ''}>Texas</option>
                  </select>
                </div>
                <div class="oncosure-form-group">
                  <label>ZIP Code *</label>
                  <input type="text" placeholder="12345" data-field="billingZip"
                         value="${this.formData.billingZip}"
                         onchange="oncoSureForm.updateFormData('billingZip', this.value)">
                </div>
              </div>
            ` : ''}
          </div>

          <!-- Order Total -->
          <div class="oncosure-order-total">
            <div class="oncosure-total-header">
              <h4>Order Total</h4>
              <span class="oncosure-total-amount">$${this.calculateTotal().toLocaleString()}</span>
            </div>
            ${this.formData.specialInstructions ? `
              <div class="oncosure-special-notes">
                <span>Special Instructions:</span>
                <p>${this.formData.specialInstructions}</p>
              </div>
            ` : ''}
          </div>
        </div>

        <div class="oncosure-step-navigation">
          <button class="oncosure-btn oncosure-btn-secondary" onclick="oncoSureForm.prevStep()">Back</button>
          <button class="oncosure-btn oncosure-btn-primary" onclick="oncoSureForm.validateStep4WithTooltip()">
            Place Order - $${this.calculateTotal().toLocaleString()}
          </button>
          <div class="oncosure-button-tooltip" id="button-tooltip" style="display: none;"></div>
        </div>
      </div>
    `;
  }

  renderStep5() {
    return `
      <div class="oncosure-step-section">
        <div class="oncosure-step-header">
          <div class="oncosure-step-icon-circle">
            <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
          </div>
          <div class="oncosure-step-title-section">
            <h3>Your Order</h3>
            <p>Order summary and confirmation details</p>
          </div>
        </div>

        <div class="oncosure-order-sections">
          <!-- Selected Tests -->
          <div class="oncosure-order-section">
            <h4>Selected Tests</h4>
            <div class="oncosure-selected-tests">
              ${this.formData.selectedTests.map(testId => {
                const test = this.testOptions.find(t => t.id === testId);
                return test ? `
                  <div class="oncosure-test-summary">
                    <div class="oncosure-test-details">
                      <div class="oncosure-test-name-row">
                        <h5>${test.name}</h5>
                        ${test.badge ? `<span class="oncosure-badge">${test.badge}</span>` : ''}
                      </div>
                      <p>${test.description}</p>
                    </div>
                    <div class="oncosure-test-price">
                      <span class="oncosure-original-price">$${test.originalPrice.toLocaleString()}</span>
                      <span class="oncosure-sale-price">$${test.salePrice.toLocaleString()}</span>
                      <p>${test.duration}</p>
                    </div>
                  </div>
                ` : '';
              }).join('')}

              ${this.formData.rushProcessing ? `
                <div class="oncosure-test-summary">
                  <div class="oncosure-test-details">
                    <h5>Rush Processing</h5>
                    <p>Get results in 24-48 hours</p>
                  </div>
                  <div class="oncosure-test-price">
                    <span class="oncosure-sale-price">$500</span>
                  </div>
                </div>
              ` : ''}
            </div>
          </div>

          <!-- Patient Information -->
          <div class="oncosure-order-section">
            <h4>Patient Information</h4>
            <div class="oncosure-info-grid">
              <div><span>Name:</span> ${this.formData.firstName} ${this.formData.lastName}</div>
              <div><span>DOB:</span> ${this.formData.dateOfBirth}</div>
              <div><span>Gender:</span> ${this.formData.gender}</div>
              <div><span>Phone:</span> ${this.formData.phoneNumber}</div>
              <div><span>Email:</span> ${this.formData.emailAddress}</div>
              <div><span>Address:</span> ${this.formData.address}, ${this.formData.city}, ${this.formData.state} ${this.formData.zipCode}</div>
            </div>
          </div>

          <!-- Agreements -->
          <div class="oncosure-order-section">
            <h4>Agreements & Consent</h4>
            <div class="oncosure-agreement-status">
              <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
              </svg>
              <span>All agreements and policies have been accepted</span>
            </div>
          </div>

          <!-- Payment Information -->
          <div class="oncosure-order-section">
            <h4>Payment Method</h4>
            <div class="oncosure-payment-summary">
              ${this.formData.paymentMethod === 'credit_card' ? `
                <div>
                  <span>Credit/Debit Card ending in</span>
                  <span>••••${this.formData.cardNumber ? this.formData.cardNumber.slice(-4) : '••••'}</span>
                  <br>
                  <span>Cardholder:</span>
                  <span>${this.formData.cardholderName}</span>
                </div>
              ` : this.formData.paymentMethod === 'affirm' ? `
                <div>
                  <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1" />
                  </svg>
                  <span>Affirm - Pay over time</span>
                </div>
              ` : `
                <span>No payment method selected</span>
              `}
            </div>
          </div>
        </div>

        <div class="oncosure-step-navigation">
          <button class="oncosure-btn oncosure-btn-secondary" onclick="oncoSureForm.prevStep()">Back</button>
          <div class="oncosure-action-buttons">
            <button class="oncosure-btn oncosure-btn-outline" onclick="window.print()">
              <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
              </svg>
              Print
            </button>
            <button class="oncosure-btn oncosure-btn-primary" onclick="alert('Save functionality would be implemented here')">
              <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
              </svg>
              Save
            </button>
          </div>
        </div>
      </div>
    `;
  }

  renderOrderSummary() {
    if (this.formData.selectedTests.length === 0) {
      return `
        <div class="oncosure-empty-summary">
          <svg width="48" height="48" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
          </svg>
          <p>Select tests to see pricing</p>
        </div>
      `;
    }

    return `
      <div class="oncosure-summary-items">
        ${this.formData.selectedTests.map(testId => {
          const test = this.testOptions.find(t => t.id === testId);
          return test ? `
            <div class="oncosure-summary-item">
              <span>${test.name}</span>
              <span>$${test.salePrice.toLocaleString()}</span>
            </div>
          ` : '';
        }).join('')}
        
        ${this.formData.rushProcessing ? `
          <div class="oncosure-summary-item">
            <span>Rush Processing</span>
            <span>$500</span>
          </div>
        ` : ''}
        
        <div class="oncosure-summary-total">
          <span>Total</span>
          <span>$${this.calculateTotal().toLocaleString()}</span>
        </div>
      </div>
    `;
  }

  // Event Handlers
  handleStepClick(stepNumber) {
    const tooltip = this.getTooltipMessage(stepNumber);
    if (tooltip) {
      this.showTooltip(stepNumber, tooltip);
    } else {
      this.currentStep = stepNumber;
      this.renderForm();
    }
  }

  handleTestSelection(testId) {
    if (this.formData.selectedTests.includes(testId)) {
      this.formData.selectedTests = this.formData.selectedTests.filter(id => id !== testId);
    } else {
      this.formData.selectedTests.push(testId);
    }
    this.renderForm();
  }

  updateFormData(field, value) {
    this.formData[field] = value;
    this.renderForm();
  }

  nextStep() {
    if (this.currentStep < 5) {
      this.currentStep++;
      this.renderForm();
    }
  }

  prevStep() {
    if (this.currentStep > 1) {
      this.currentStep--;
      this.renderForm();
    }
  }

  // Validation Methods
  validateStep1WithTooltip() {
    if (this.formData.selectedTests.length === 0) {
      this.showButtonTooltip("Please select a test first");
    } else {
      this.nextStep();
    }
  }

  validateStep2WithTooltip() {
    if (this.validateStep2()) {
      this.nextStep();
    } else {
      const tooltip = this.getTooltipMessage(3);
      if (tooltip) {
        this.showButtonTooltip(tooltip);
      }
    }
  }

  validateStep3WithTooltip() {
    if (this.formData.allAgreements) {
      this.nextStep();
    } else {
      const tooltip = this.getTooltipMessage(4);
      if (tooltip) {
        this.showButtonTooltip(tooltip);
      }
    }
  }

  validateStep4WithTooltip() {
    const hasPaymentMethod = this.formData.paymentMethod !== '';
    const hasCardDetails = this.formData.paymentMethod !== 'credit_card' ||
      (this.formData.cardholderName && this.formData.cardNumber && this.formData.expiryDate && this.formData.cvv);
    const hasBillingInfo = this.formData.sameAsPatient ||
      (this.formData.billingAddress && this.formData.billingCity && this.formData.billingState && this.formData.billingZip);

    if (hasPaymentMethod && hasCardDetails && hasBillingInfo) {
      this.nextStep();
    } else {
      const tooltip = this.getTooltipMessage(5);
      if (tooltip) {
        this.showButtonTooltip(tooltip);
      }
    }
  }

  validateStep2() {
    const requiredFields = ['firstName', 'lastName', 'dateOfBirth', 'gender', 'phoneNumber', 'emailAddress', 'address', 'city', 'state', 'zipCode'];
    return requiredFields.every(field => this.formData[field] && this.formData[field].trim() !== '');
  }

  // Utility Methods
  calculateTotal() {
    let total = 0;
    this.formData.selectedTests.forEach(testId => {
      const test = this.testOptions.find(t => t.id === testId);
      if (test) total += test.salePrice;
    });
    if (this.formData.rushProcessing) total += 500;
    return total;
  }

  getTooltipMessage(stepNumber) {
    if (stepNumber === 1) return null;

    if (stepNumber === 2) {
      if (this.formData.selectedTests.length === 0) {
        return "Please select a test first";
      }
      return null;
    }

    if (stepNumber === 3) {
      if (this.formData.selectedTests.length === 0) {
        return "Please select a test first";
      }

      const missingFields = [];
      if (!this.formData.firstName) missingFields.push("First Name");
      if (!this.formData.lastName) missingFields.push("Last Name");
      if (!this.formData.dateOfBirth) missingFields.push("Date of Birth");
      if (!this.formData.gender) missingFields.push("Gender");
      if (!this.formData.phoneNumber) missingFields.push("Phone Number");
      if (!this.formData.emailAddress) missingFields.push("Email Address");
      if (!this.formData.address) missingFields.push("Address");
      if (!this.formData.city) missingFields.push("City");
      if (!this.formData.state) missingFields.push("State");
      if (!this.formData.zipCode) missingFields.push("ZIP Code");

      if (missingFields.length > 0) {
        return `Please complete: ${missingFields.slice(0, 2).join(", ")}${missingFields.length > 2 ? ` +${missingFields.length - 2} more` : ""}`;
      }
    }

    if (stepNumber >= 4) {
      if (this.formData.selectedTests.length === 0) {
        return "Please select a test first";
      }

      const missingFields = [];
      if (!this.formData.firstName) missingFields.push("First Name");
      if (!this.formData.lastName) missingFields.push("Last Name");
      if (!this.formData.dateOfBirth) missingFields.push("Date of Birth");
      if (!this.formData.gender) missingFields.push("Gender");
      if (!this.formData.phoneNumber) missingFields.push("Phone Number");
      if (!this.formData.emailAddress) missingFields.push("Email Address");
      if (!this.formData.address) missingFields.push("Address");
      if (!this.formData.city) missingFields.push("City");
      if (!this.formData.state) missingFields.push("State");
      if (!this.formData.zipCode) missingFields.push("ZIP Code");

      if (missingFields.length > 0) {
        return `Please complete: ${missingFields.slice(0, 2).join(", ")}${missingFields.length > 2 ? ` +${missingFields.length - 2} more` : ""}`;
      }

      if (!this.formData.allAgreements) {
        return "Please accept all agreements to continue";
      }
    }

    return null;
  }

  showTooltip(stepNumber, message) {
    // Hide all tooltips first
    document.querySelectorAll('.oncosure-tooltip').forEach(tooltip => {
      tooltip.style.display = 'none';
    });

    // Show the specific tooltip
    const tooltip = document.getElementById(`tooltip-${stepNumber}`);
    if (tooltip) {
      tooltip.textContent = message;
      tooltip.style.display = 'block';
      setTimeout(() => {
        tooltip.style.display = 'none';
      }, 3000);
    }
  }

  showButtonTooltip(message) {
    const tooltip = document.getElementById('button-tooltip');
    if (tooltip) {
      tooltip.textContent = message;
      tooltip.style.display = 'block';
      setTimeout(() => {
        tooltip.style.display = 'none';
      }, 3000);
    }
  }

  attachEventListeners() {
    // Event listeners are attached via onclick attributes in the HTML
  }
}

// Initialize the form when the page loads
let oncoSureForm;

function initOncoSureForm() {
  try {
    const container = document.querySelector('.oncosure-form-container');
    if (container) {
      console.log('OncoSure: Form container found, initializing...');
      oncoSureForm = new OncoSureForm();
      console.log('OncoSure: Form initialized successfully');
    } else {
      console.log('OncoSure: Form container not found, retrying...');
      setTimeout(initOncoSureForm, 100);
    }
  } catch (error) {
    console.error('OncoSure: Error initializing form:', error);
  }
}

// Multiple initialization attempts
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initOncoSureForm);
} else {
  initOncoSureForm();
}

// Also try after a short delay
setTimeout(initOncoSureForm, 500);
setTimeout(initOncoSureForm, 1000);