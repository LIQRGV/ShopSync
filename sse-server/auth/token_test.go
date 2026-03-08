package auth

import (
	"crypto/hmac"
	"crypto/sha256"
	"encoding/base64"
	"encoding/hex"
	"encoding/json"
	"testing"
	"time"
)

const testAppKey = "base64:dGVzdC1hcHAta2V5LWZvci1zaG9wc3luYw=="

// makeToken creates a token in the same format as PHP:
// base64(json_payload).hex_hmac_sha256_signature
func makeToken(payload TokenPayload, appKey string) string {
	jsonBytes, err := json.Marshal(payload)
	if err != nil {
		panic("failed to marshal test payload: " + err.Error())
	}

	b64 := base64.StdEncoding.EncodeToString(jsonBytes)

	mac := hmac.New(sha256.New, []byte(appKey))
	mac.Write([]byte(b64))
	sig := hex.EncodeToString(mac.Sum(nil))

	return b64 + "." + sig
}

// fixedClock returns a clock function that always returns the given time.
func fixedClock(t time.Time) func() time.Time {
	return func() time.Time { return t }
}

func TestValidateValidWLToken(t *testing.T) {
	now := time.Date(2026, 3, 1, 12, 0, 0, 0, time.UTC)

	payload := TokenPayload{
		Mode:          "wl",
		ClientID:      nil,
		UpstreamURL:   "",
		UpstreamToken: "",
		Iat:           now.Unix(),
		Exp:           now.Add(60 * time.Second).Unix(),
	}
	token := makeToken(payload, testAppKey)

	v := NewTokenValidator(testAppKey)
	v.SetClock(fixedClock(now))

	result, err := v.Validate(token)
	if err != nil {
		t.Fatalf("unexpected error: %v", err)
	}
	if result.Mode != "wl" {
		t.Errorf("Mode: got %q, want %q", result.Mode, "wl")
	}
	if result.ClientID != nil {
		t.Errorf("ClientID: got %v, want nil", result.ClientID)
	}
	if result.UpstreamURL != "" {
		t.Errorf("UpstreamURL: got %q, want %q", result.UpstreamURL, "")
	}
	if result.UpstreamToken != "" {
		t.Errorf("UpstreamToken: got %q, want %q", result.UpstreamToken, "")
	}
	if result.Iat != now.Unix() {
		t.Errorf("Iat: got %d, want %d", result.Iat, now.Unix())
	}
	if result.Exp != now.Add(60*time.Second).Unix() {
		t.Errorf("Exp: got %d, want %d", result.Exp, now.Add(60*time.Second).Unix())
	}
}

func TestValidateValidWTMToken(t *testing.T) {
	now := time.Date(2026, 3, 1, 12, 0, 0, 0, time.UTC)
	clientID := 42

	payload := TokenPayload{
		Mode:          "wtm",
		ClientID:      &clientID,
		UpstreamURL:   "https://upstream.example.com/api/v1",
		UpstreamToken: "upstream-bearer-token-abc123",
		Iat:           now.Unix(),
		Exp:           now.Add(60 * time.Second).Unix(),
	}
	token := makeToken(payload, testAppKey)

	v := NewTokenValidator(testAppKey)
	v.SetClock(fixedClock(now))

	result, err := v.Validate(token)
	if err != nil {
		t.Fatalf("unexpected error: %v", err)
	}
	if result.Mode != "wtm" {
		t.Errorf("Mode: got %q, want %q", result.Mode, "wtm")
	}
	if result.ClientID == nil {
		t.Fatal("ClientID: got nil, want non-nil")
	}
	if *result.ClientID != 42 {
		t.Errorf("ClientID: got %d, want %d", *result.ClientID, 42)
	}
	if result.UpstreamURL != "https://upstream.example.com/api/v1" {
		t.Errorf("UpstreamURL: got %q, want %q", result.UpstreamURL, "https://upstream.example.com/api/v1")
	}
	if result.UpstreamToken != "upstream-bearer-token-abc123" {
		t.Errorf("UpstreamToken: got %q, want %q", result.UpstreamToken, "upstream-bearer-token-abc123")
	}
}

func TestValidateInvalidSignature(t *testing.T) {
	now := time.Date(2026, 3, 1, 12, 0, 0, 0, time.UTC)

	payload := TokenPayload{
		Mode: "wl",
		Iat:  now.Unix(),
		Exp:  now.Add(60 * time.Second).Unix(),
	}
	// Create token with one key, validate with another
	token := makeToken(payload, "wrong-key")

	v := NewTokenValidator(testAppKey)
	v.SetClock(fixedClock(now))

	_, err := v.Validate(token)
	if err == nil {
		t.Fatal("expected error for invalid signature, got nil")
	}
	if err.Error() != "invalid signature" {
		t.Errorf("error: got %q, want %q", err.Error(), "invalid signature")
	}
}

func TestValidateExpiredToken(t *testing.T) {
	issueTime := time.Date(2026, 3, 1, 12, 0, 0, 0, time.UTC)

	payload := TokenPayload{
		Mode: "wl",
		Iat:  issueTime.Unix(),
		Exp:  issueTime.Add(60 * time.Second).Unix(),
	}
	token := makeToken(payload, testAppKey)

	v := NewTokenValidator(testAppKey)
	// Set clock to 2 minutes after issue -- token expired 1 minute ago
	v.SetClock(fixedClock(issueTime.Add(2 * time.Minute)))

	_, err := v.Validate(token)
	if err == nil {
		t.Fatal("expected error for expired token, got nil")
	}
	if err.Error() != "token expired" {
		t.Errorf("error: got %q, want %q", err.Error(), "token expired")
	}
}

func TestValidateTokenAtExactExpiry(t *testing.T) {
	now := time.Date(2026, 3, 1, 12, 0, 0, 0, time.UTC)
	expiry := now.Add(60 * time.Second)

	payload := TokenPayload{
		Mode: "wl",
		Iat:  now.Unix(),
		Exp:  expiry.Unix(),
	}
	token := makeToken(payload, testAppKey)

	v := NewTokenValidator(testAppKey)
	// Clock is exactly at expiry -- Unix() == Exp, so NOT expired (> check, not >=)
	v.SetClock(fixedClock(expiry))

	result, err := v.Validate(token)
	if err != nil {
		t.Fatalf("token at exact expiry should be valid, got error: %v", err)
	}
	if result.Mode != "wl" {
		t.Errorf("Mode: got %q, want %q", result.Mode, "wl")
	}
}

func TestValidateTokenOneSecondAfterExpiry(t *testing.T) {
	now := time.Date(2026, 3, 1, 12, 0, 0, 0, time.UTC)
	expiry := now.Add(60 * time.Second)

	payload := TokenPayload{
		Mode: "wl",
		Iat:  now.Unix(),
		Exp:  expiry.Unix(),
	}
	token := makeToken(payload, testAppKey)

	v := NewTokenValidator(testAppKey)
	// Clock is one second past expiry
	v.SetClock(fixedClock(expiry.Add(1 * time.Second)))

	_, err := v.Validate(token)
	if err == nil {
		t.Fatal("expected error for token 1 second past expiry, got nil")
	}
	if err.Error() != "token expired" {
		t.Errorf("error: got %q, want %q", err.Error(), "token expired")
	}
}

func TestValidateInvalidFormat(t *testing.T) {
	tests := []struct {
		name  string
		token string
	}{
		{name: "no dot separator", token: "nodothere"},
		{name: "empty string", token: ""},
		{name: "dot only", token: "."},
		{name: "empty payload", token: ".abcdef1234567890"},
		{name: "empty signature", token: "abcdef1234567890."},
		{name: "multiple dots treated as two parts", token: "a.b.c"},
	}

	v := NewTokenValidator(testAppKey)
	v.SetClock(fixedClock(time.Date(2026, 3, 1, 12, 0, 0, 0, time.UTC)))

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			_, err := v.Validate(tt.token)
			if err == nil {
				t.Fatalf("expected error for token %q, got nil", tt.token)
			}
			// All of these should fail at format or signature check
		})
	}
}

func TestValidateInvalidBase64(t *testing.T) {
	// Create a token where the payload part is not valid base64
	// but the HMAC signature is correct for that (invalid) payload string
	invalidB64 := "!!!not-base64!!!"

	mac := hmac.New(sha256.New, []byte(testAppKey))
	mac.Write([]byte(invalidB64))
	sig := hex.EncodeToString(mac.Sum(nil))

	token := invalidB64 + "." + sig

	v := NewTokenValidator(testAppKey)
	v.SetClock(fixedClock(time.Date(2026, 3, 1, 12, 0, 0, 0, time.UTC)))

	_, err := v.Validate(token)
	if err == nil {
		t.Fatal("expected error for invalid base64, got nil")
	}
	if err.Error() != "invalid base64 payload" {
		t.Errorf("error: got %q, want %q", err.Error(), "invalid base64 payload")
	}
}

func TestValidateInvalidJSON(t *testing.T) {
	// Valid base64 but the decoded content is not valid JSON
	notJSON := "this is not json"
	b64 := base64.StdEncoding.EncodeToString([]byte(notJSON))

	mac := hmac.New(sha256.New, []byte(testAppKey))
	mac.Write([]byte(b64))
	sig := hex.EncodeToString(mac.Sum(nil))

	token := b64 + "." + sig

	v := NewTokenValidator(testAppKey)
	v.SetClock(fixedClock(time.Date(2026, 3, 1, 12, 0, 0, 0, time.UTC)))

	_, err := v.Validate(token)
	if err == nil {
		t.Fatal("expected error for invalid JSON, got nil")
	}
	if err.Error() != "invalid JSON payload" {
		t.Errorf("error: got %q, want %q", err.Error(), "invalid JSON payload")
	}
}

func TestValidateInvalidMode(t *testing.T) {
	now := time.Date(2026, 3, 1, 12, 0, 0, 0, time.UTC)

	payload := TokenPayload{
		Mode: "invalid_mode",
		Iat:  now.Unix(),
		Exp:  now.Add(60 * time.Second).Unix(),
	}
	token := makeToken(payload, testAppKey)

	v := NewTokenValidator(testAppKey)
	v.SetClock(fixedClock(now))

	_, err := v.Validate(token)
	if err == nil {
		t.Fatal("expected error for invalid mode, got nil")
	}
	if err.Error() != "invalid mode" {
		t.Errorf("error: got %q, want %q", err.Error(), "invalid mode")
	}
}

func TestValidateEmptyMode(t *testing.T) {
	now := time.Date(2026, 3, 1, 12, 0, 0, 0, time.UTC)

	payload := TokenPayload{
		Mode: "",
		Iat:  now.Unix(),
		Exp:  now.Add(60 * time.Second).Unix(),
	}
	token := makeToken(payload, testAppKey)

	v := NewTokenValidator(testAppKey)
	v.SetClock(fixedClock(now))

	_, err := v.Validate(token)
	if err == nil {
		t.Fatal("expected error for empty mode, got nil")
	}
	if err.Error() != "invalid mode" {
		t.Errorf("error: got %q, want %q", err.Error(), "invalid mode")
	}
}

func TestValidateTamperedPayload(t *testing.T) {
	now := time.Date(2026, 3, 1, 12, 0, 0, 0, time.UTC)

	// Create a valid token
	payload := TokenPayload{
		Mode: "wl",
		Iat:  now.Unix(),
		Exp:  now.Add(60 * time.Second).Unix(),
	}
	token := makeToken(payload, testAppKey)

	// Tamper with the payload: create a different payload but keep the original signature
	tampered := TokenPayload{
		Mode: "wtm",
		Iat:  now.Unix(),
		Exp:  now.Add(3600 * time.Second).Unix(), // extend expiry
	}
	tamperedJSON, _ := json.Marshal(tampered)
	tamperedB64 := base64.StdEncoding.EncodeToString(tamperedJSON)

	// Use original signature with tampered payload
	parts := splitToken(token)
	tamperedToken := tamperedB64 + "." + parts[1]

	v := NewTokenValidator(testAppKey)
	v.SetClock(fixedClock(now))

	_, err := v.Validate(tamperedToken)
	if err == nil {
		t.Fatal("expected error for tampered payload, got nil")
	}
	if err.Error() != "invalid signature" {
		t.Errorf("error: got %q, want %q", err.Error(), "invalid signature")
	}
}

// splitToken is a test helper that splits a token into [payload, signature].
func splitToken(token string) [2]string {
	for i, c := range token {
		if c == '.' {
			return [2]string{token[:i], token[i+1:]}
		}
	}
	return [2]string{token, ""}
}

func TestValidateMultipleDots(t *testing.T) {
	// SplitN with limit 2 means "a.b.c" becomes ["a", "b.c"]
	// "b.c" is not a valid hex HMAC (contains a dot), so it should fail
	// at signature verification.
	v := NewTokenValidator(testAppKey)
	v.SetClock(fixedClock(time.Date(2026, 3, 1, 12, 0, 0, 0, time.UTC)))

	_, err := v.Validate("part1.part2.part3")
	if err == nil {
		t.Fatal("expected error for token with multiple dots, got nil")
	}
	// Should fail at signature verification since "part2.part3" is not valid HMAC
	if err.Error() != "invalid signature" {
		t.Errorf("error: got %q, want %q", err.Error(), "invalid signature")
	}
}

func TestValidateHMACHexEncoding(t *testing.T) {
	// Verify that our token generation produces the same format as PHP.
	// PHP: hash_hmac('sha256', $base64_payload, $key) returns 64 hex chars.
	now := time.Date(2026, 3, 1, 12, 0, 0, 0, time.UTC)

	payload := TokenPayload{
		Mode: "wl",
		Iat:  now.Unix(),
		Exp:  now.Add(60 * time.Second).Unix(),
	}
	token := makeToken(payload, testAppKey)
	parts := splitToken(token)
	sig := parts[1]

	// HMAC-SHA256 hex output should be exactly 64 characters
	if len(sig) != 64 {
		t.Errorf("HMAC signature length: got %d, want 64", len(sig))
	}

	// Verify all characters are valid hex
	for i, c := range sig {
		if !((c >= '0' && c <= '9') || (c >= 'a' && c <= 'f')) {
			t.Errorf("Non-hex character at position %d: %c", i, c)
		}
	}
}

func TestNewTokenValidatorDefaultClock(t *testing.T) {
	v := NewTokenValidator(testAppKey)

	// The default clock should return approximately the current time
	clockTime := v.clock()
	diff := time.Since(clockTime)
	if diff < 0 {
		diff = -diff
	}
	if diff > 1*time.Second {
		t.Errorf("Default clock drift too large: %v", diff)
	}
}

func TestValidateStdBase64Encoding(t *testing.T) {
	// Verify that tokens use standard base64 (with +, /, =) not URL-safe base64.
	// Create a payload whose JSON, when base64-encoded, will contain + or / characters.
	// We test that StdEncoding is used by the validator.
	now := time.Date(2026, 3, 1, 12, 0, 0, 0, time.UTC)

	payload := TokenPayload{
		Mode:          "wl",
		UpstreamURL:   "https://example.com/path?foo=bar&baz=qux",
		UpstreamToken: "token+with/special==chars",
		Iat:           now.Unix(),
		Exp:           now.Add(60 * time.Second).Unix(),
	}

	// Manually create token using StdEncoding (same as PHP)
	jsonBytes, _ := json.Marshal(payload)
	b64 := base64.StdEncoding.EncodeToString(jsonBytes)

	mac := hmac.New(sha256.New, []byte(testAppKey))
	mac.Write([]byte(b64))
	sig := hex.EncodeToString(mac.Sum(nil))

	token := b64 + "." + sig

	v := NewTokenValidator(testAppKey)
	v.SetClock(fixedClock(now))

	result, err := v.Validate(token)
	if err != nil {
		t.Fatalf("unexpected error with StdEncoding token: %v", err)
	}
	if result.UpstreamToken != "token+with/special==chars" {
		t.Errorf("UpstreamToken: got %q, want %q", result.UpstreamToken, "token+with/special==chars")
	}
}
