//+------------------------------------------------------------------+
//|                                           AURAAI.mq5             |
//|                                 Copyright 2025, Peace TheeCoder |
//+------------------------------------------------------------------+
#property copyright "Copyright 2025."
#property link      "https://auraai-vps.com"
#property version   "1.06"

input string eaCodeInput = "";  // EA secret code from auraai-vps.com admin dashboard

//+------------------------------------------------------------------+
string TrimEaCode(string code) {
   StringTrimLeft(code);
   StringTrimRight(code);
   return code;
}

struct Order {
   int id;
   ulong identifier;
   string asset;
   string action;
   string price;
   string tp;
   string sl;
   string lot;
};

Order Signal = {};
datetime lastutime = 0;
bool isMartingaleBot = false;

/** Positions already published successfully (do not rely on a single Signal.identifier). */
ulong g_publishedIds[];

//+------------------------------------------------------------------+
bool IsPositionPublished(const ulong pos_id) {
   int n = ArraySize(g_publishedIds);
   for(int i = 0; i < n; i++) {
      if(g_publishedIds[i] == pos_id)
         return true;
   }
   return false;
}

//+------------------------------------------------------------------+
void MarkPositionPublished(const ulong pos_id) {
   if(IsPositionPublished(pos_id))
      return;
   int n = ArraySize(g_publishedIds);
   ArrayResize(g_publishedIds, n + 1);
   g_publishedIds[n] = pos_id;
}

//+------------------------------------------------------------------+
int OnInit() {
   lastutime = TimeCurrent();
   ArrayResize(g_publishedIds, 0);
   string url = "https://auraai-vps.com/admin/";
   string eaCode = TrimEaCode(eaCodeInput);

   if(eaCode == "") {
      MessageBox("Enter your EA secret code from auraai-vps.com admin (EA detail page).", "Aura AI", MB_ICONINFORMATION);
      Print("Login failed: empty eaCodeInput");
      return(INIT_FAILED);
   }

   if(!login(url, eaCode)) {
      Print("Login failed for eaCodeInput: ", eaCode);
      return(INIT_FAILED);
   }

   Signal.id = 0;
   Signal.identifier = 0;
   Signal.asset = "";
   Signal.action = "";
   Signal.price = "";
   Signal.tp = "";
   Signal.sl = "";
   Signal.lot = "";

   ObjectsDeleteAll(0, -1, -1);
   Print("Martingale bot: ", isMartingaleBot ? "yes" : "no");
   Print("EA Initialized on symbol: ", Symbol(), " (AURAAI v1.06)");
   return(INIT_SUCCEEDED);
}

//+------------------------------------------------------------------+
void OnDeinit(const int reason) {
   ObjectsDeleteAll(0, -1, -1);
}

//+------------------------------------------------------------------+
void OnTick() {
   Comment("Aura AI — publishing open positions (SL/TP optional).\nMonitoring…");
   CheckPositions();
}

//+------------------------------------------------------------------+
int VolumeDigits(const string symbol) {
   double volStep = SymbolInfoDouble(symbol, SYMBOL_VOLUME_STEP);
   if(volStep <= 0.0)
      return 2;
   return (int)MathMax(0, MathRound(-MathLog10(volStep)));
}

//+------------------------------------------------------------------+
void CheckPositions() {
   if(PositionsTotal() == 0) return;

   for(int i = 0; i < PositionsTotal(); i++) {
      ulong ticket = PositionGetTicket(i);
      if(!PositionSelectByTicket(ticket)) {
         Print("Failed to select position by ticket: ", ticket, ", Error: ", GetLastError());
         continue;
      }

      ulong pos_id = PositionGetInteger(POSITION_IDENTIFIER);

      if(IsPositionPublished(pos_id))
         continue;

      string pos_asset = PositionGetString(POSITION_SYMBOL);
      int symDigits = (int)SymbolInfoInteger(pos_asset, SYMBOL_DIGITS);
      string pos_action = (PositionGetInteger(POSITION_TYPE) == POSITION_TYPE_BUY) ? "buy" : "sell";
      string pos_price = DoubleToString(PositionGetDouble(POSITION_PRICE_OPEN), symDigits);
      string pos_tp = DoubleToString(PositionGetDouble(POSITION_TP), symDigits);
      string pos_sl = DoubleToString(PositionGetDouble(POSITION_SL), symDigits);
      string pos_lot = "";

      // Always capture volume — followers need it for martingale; harmless for standard bots.
      pos_lot = DoubleToString(
         PositionGetDouble(POSITION_VOLUME),
         VolumeDigits(pos_asset)
      );

      Print("Position ", i, ": ticket=", ticket, ", symbol=", pos_asset,
            ", action=", pos_action, ", price=", pos_price, ", tp=", pos_tp, ", sl=", pos_sl,
            ", lot=", pos_lot);

      // Copy all new opens — SL/TP optional (0.0 is allowed and sent as "0").
      bool throttleOk = (TimeCurrent() - lastutime >= 2);

      if(throttleOk) {
         Signal.asset = pos_asset;
         Signal.action = pos_action;
         Signal.price = pos_price;
         Signal.tp = pos_tp;
         Signal.sl = pos_sl;
         Signal.lot = pos_lot;
         Signal.identifier = pos_id;

         Print("Signal Prepared: asset=", Signal.asset,
               ", action=", Signal.action,
               ", price=", Signal.price,
               ", tp=", Signal.tp,
               ", sl=", Signal.sl,
               ", lot=", Signal.lot,
               ", identifier=", Signal.identifier);

         if(SendSignal(TrimEaCode(eaCodeInput))) {
            MarkPositionPublished(pos_id);
            lastutime = TimeCurrent();
            Print("=== Position ", pos_id, " marked published ===");
         } else {
            // Do NOT mark published — retry on next ticks until accept.
            Print("=== Publish failed for ", pos_id, " — will retry ===");
         }
      } else {
         Print("Signal skipped (throttle): TP=", pos_tp, ", SL=", pos_sl,
               ", Time since last signal=", TimeCurrent() - lastutime);
      }
   }
}

//+------------------------------------------------------------------+
void StringToUtf8Payload(const string text, uchar &out[]) {
   int n = StringToCharArray(text, out, 0, WHOLE_ARRAY, CP_UTF8);
   if(n > 0 && out[n - 1] == 0)
      ArrayResize(out, n - 1);
}

//+------------------------------------------------------------------+
bool SendSignal(string ea_code) {
   string url = "https://auraai-vps.com/admin/api/signals/";
   string lotField = "";
   if(Signal.lot != "" && Signal.lot != "0")
      lotField = ",\"lot\":\"" + Signal.lot + "\"";

   string reqString = "{\"ea_secret\":\"" + ea_code + "\",\"signal\":{\"asset\":\"" + Signal.asset +
                     "\",\"type\":\"all\",\"action\":\"" + Signal.action + "\",\"price\":\"" +
                     Signal.price + "\",\"tp\":\"" + Signal.tp + "\",\"sl\":\"" + Signal.sl + "\"" + lotField + "}}";

   uchar jsonData[];
   StringToUtf8Payload(reqString, jsonData);

   uchar serverResult[];
   string serverHeaders;
   string requestHeaders = "Content-Type: application/json\r\nAccept: application/json";

   int res = WebRequest("POST", url, requestHeaders, 10000, jsonData, serverResult, serverHeaders);
   string response = CharArrayToString(serverResult, 0, WHOLE_ARRAY, CP_UTF8);
   Print("Server response: ", response);

   if(res >= 200 && res < 300 && StringFind(response, "\"message\":\"accept\"") >= 0) {
      Print("=== Signal saved ===");
      Print("HTTP ", res, " — ", response);
      return true;
   }

   Print("=== Signal NOT saved ===");
   Print("HTTP ", res, " — ", response);
   Print("Check: symbol must be added to this EA on auraai-vps.com admin, and WebRequest allowed for https://auraai-vps.com");
   Print("WebRequest error: ", GetLastError());
   return false;
}

//+------------------------------------------------------------------+
bool login(string url, string key) {
   key = TrimEaCode(key);
   string requestHeaders = "Accept: application/json\r\n";
   uchar postData[];
   uchar resultData[];
   string resultHeaders;

   string urlm = url + "auth.php?key=" + key;
   ResetLastError();

   int res = WebRequest("GET", urlm, requestHeaders, 15000, postData, resultData, resultHeaders);
   string responseBody = CharArrayToString(resultData, 0, WHOLE_ARRAY, CP_UTF8);

   if(res == -1) {
      int err = GetLastError();
      Print("Error in WebRequest. Error code =", err, " URL=", urlm);
      MessageBox("Allow WebRequest for https://auraai-vps.com in Tools > Options > Expert Advisors, then re-attach the EA.", "WebRequest blocked", MB_ICONINFORMATION);
      return false;
   }

   Print("Auth response HTTP ", res, " body: ", responseBody);

   if(res >= 200 && res < 300) {
      Print("SUCCESSFULLY LOGGED IN (HTTP ", res, ")");
      isMartingaleBot = (StringFind(responseBody, "\"martingale\":1") >= 0 ||
                         StringFind(responseBody, "\"martingale\": 1") >= 0 ||
                         StringFind(responseBody, "\"ea_martingale\":true") >= 0 ||
                         StringFind(responseBody, "\"ea_martingale\":1") >= 0);
      Print("Martingale bot flag from server: ", isMartingaleBot ? "yes" : "no");
      return true;
   }

   Print("Login HTTP ", res, " body: ", responseBody);

   switch(res) {
      case 404:
         MessageBox("INVALID URL", "Error", MB_ICONINFORMATION);
         break;
      case 503:
         MessageBox("Account not active", "Error", MB_ICONINFORMATION);
         break;
      case 403:
         MessageBox("Internet connection error", "Error", MB_ICONINFORMATION);
         break;
      case 203:
         MessageBox("Invalid EA Key", "Error", MB_ICONINFORMATION);
         break;
      default:
         MessageBox("Login failed (HTTP " + IntegerToString(res) + ")", "Error", MB_ICONINFORMATION);
   }
   return false;
}
