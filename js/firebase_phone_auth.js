/**
 * Firebase Phone Authentication Helper
 * Project: ain-shames-sunday-school
 */

const firebaseConfig = {
  apiKey: "AIzaSyB392IoRaad8H5JOBYpawJQE2a_a3Wkcy8",
  authDomain: "ain-shames-sunday-school.firebaseapp.com",
  databaseURL: "https://ain-shames-sunday-school-default-rtdb.firebaseio.com",
  projectId: "ain-shames-sunday-school",
  storageBucket: "ain-shames-sunday-school.firebasestorage.app",
  messagingSenderId: "384251465276",
  appId: "1:384251465276:web:4ef955535363e62e21fd39",
  measurementId: "G-DVLYG41BNZ"
};

// Initialize Firebase if not already initialized
if (typeof firebase !== 'undefined' && !firebase.apps.length) {
  firebase.initializeApp(firebaseConfig);
}

const FirebasePhoneAuth = {
  confirmationResult: null,
  recaptchaVerifier: null,
  currentPhone: null,

  /**
   * Convert local/Egyptian phone number to standard international E.164 format (+201xxxxxxxxx)
   */
  toE164(phone) {
    if (!phone) return '';
    let clean = phone.toString().replace(/[\s\-\(\)]/g, '').trim();
    if (clean.startsWith('00')) {
      clean = '+' + clean.substring(2);
    }
    if (!clean.startsWith('+')) {
      if (clean.startsWith('01') && clean.length === 11) {
        clean = '+2' + clean;
      } else if (clean.startsWith('201') && clean.length === 12) {
        clean = '+' + clean;
      } else if (clean.startsWith('1') && clean.length === 10) {
        clean = '+20' + clean;
      } else {
        clean = '+20' + clean.replace(/^0+/, '');
      }
    }
    return clean;
  },

  /**
   * Setup or get the reCAPTCHA verifier instance
   */
  getRecaptchaVerifier(containerId = 'recaptcha-container', onSolved = null) {
    if (this.recaptchaVerifier) {
      try {
        this.recaptchaVerifier.clear();
      } catch (e) {}
      this.recaptchaVerifier = null;
    }

    const container = document.getElementById(containerId);
    if (container) {
      container.innerHTML = '';
    }

    this.recaptchaVerifier = new firebase.auth.RecaptchaVerifier(containerId, {
      size: 'invisible',
      callback: (response) => {
        if (typeof onSolved === 'function') onSolved(response);
      },
      'expired-callback': () => {
        if (this.recaptchaVerifier) {
          try { this.recaptchaVerifier.render(); } catch (e) {}
        }
      }
    });

    return this.recaptchaVerifier;
  },

  /**
   * Send SMS verification code to phone number
   */
  async sendVerificationCode(rawPhone, containerId = 'recaptcha-container') {
    const formattedPhone = this.toE164(rawPhone);
    if (!formattedPhone || formattedPhone.length < 10) {
      throw new Error('يرجى إدخال رقم هاتف صحيح');
    }

    this.currentPhone = formattedPhone;
    const verifier = this.getRecaptchaVerifier(containerId);

    try {
      const confirmationResult = await firebase.auth().signInWithPhoneNumber(formattedPhone, verifier);
      this.confirmationResult = confirmationResult;
      window.confirmationResult = confirmationResult;
      return {
        success: true,
        phone: formattedPhone,
        confirmationResult: confirmationResult
      };
    } catch (error) {
      // If reCAPTCHA expired or failed, clear verifier to allow retry
      if (this.recaptchaVerifier) {
        try { this.recaptchaVerifier.clear(); } catch (e) {}
        this.recaptchaVerifier = null;
      }
      const arMessage = this.getErrorMessage(error);
      throw new Error(arMessage);
    }
  },

  /**
   * Verify the 6-digit code entered by user
   */
  async verifyCode(code) {
    if (!this.confirmationResult) {
      throw new Error('لم يتم إرسال كود التحقق بعد، يرجى طلب كود جديد');
    }

    const cleanCode = (code || '').toString().trim().replace(/[^\d]/g, '');
    if (cleanCode.length !== 6) {
      throw new Error('كود التحقق يجب أن يتكون من 6 أرقام');
    }

    try {
      const result = await this.confirmationResult.confirm(cleanCode);
      const user = result.user;
      const idToken = await user.getIdToken();
      return {
        success: true,
        user: user,
        idToken: idToken,
        phoneNumber: user.phoneNumber || this.currentPhone
      };
    } catch (error) {
      const arMessage = this.getErrorMessage(error);
      throw new Error(arMessage);
    }
  },

  /**
   * Translate Firebase Auth error codes into friendly Arabic messages
   */
  getErrorMessage(error) {
    if (!error) return 'حدث خطأ غير معروف أثناء التحقق';
    const code = error.code || '';

    switch (code) {
      case 'auth/invalid-phone-number':
        return 'رقم الهاتف غير صالح. يرجى التأكد من كتابة الرقم بشكل صحيح.';
      case 'auth/missing-phone-number':
        return 'يرجى كتابة رقم الهاتف أولاً.';
      case 'auth/quota-exceeded':
        return 'تم تجاوز الحد الأقصى لإرسال الرسائل لليوم. يمكنك التجربة لاحقاً أو مراجعة خادم الكنيسة.';
      case 'auth/too-many-requests':
        return 'تم إجراء محاولات كثيرة في وقت قصير. يرجى الانتظار دقيقة والمحاولة مجدداً.';
      case 'auth/invalid-verification-code':
        return 'كود التحقق غير صحيح، يرجى التأكد من الأرقام الستة وإعادة المحاولة.';
      case 'auth/code-expired':
        return 'انتهت صلاحية كود التحقق. يرجى الضغط على "إعادة إرسال الكود".';
      case 'auth/captcha-check-failed':
        return 'فشل التحقق الأمني (reCAPTCHA). يرجى تحديث الصفحة والمحاولة مجدداً.';
      case 'auth/network-request-failed':
        return 'تعذر الاتصال بالخادم. يرجى التأكد من اتصالك بالإنترنت.';
      case 'auth/app-not-authorized':
        return 'النطاق (Domain) غير مضاف في قائمة النطاقات المصرح بها في Firebase Console.';
      default:
        return error.message || 'حدث خطأ أثناء معالجة الطلب، يرجى المحاولة لاحقاً.';
    }
  }
};

window.FirebasePhoneAuth = FirebasePhoneAuth;
