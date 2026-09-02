//+------------------------------------------------------------------+
//|                                                    EATRADE.mq5   |
//|                                      NexTradeAI copy-trade publisher |
//+------------------------------------------------------------------+
#property copyright "NexTradeAI"
#property link      "https://www.nextradeai.io"
#property version   "1.00"
#property description "Publishes MT5 positions to NexTradeAI signals (mentor copy trades)."

input string eaCodeInput   = "";  // Secret code — Admin → EAs → your automation
input string apiBaseInput  = "https://www.nextradeai.io/api"; // API base (no trailing slash)

//+------------------------------------------------------------------+
string TrimText(string text) {
   StringTrimLeft(text);
   StringTrimRight(text);
   return text;
}

string ApiBase() {
   string base = TrimText(apiBaseInput);
   if(StringLen(base) > 0 && StringGetCharacter(base, StringLen(base) - 1) == '/')
      base = StringSubstr(base, 0, StringLen(base) - 1);
   return base;
}

struct CopySignal {
   ulong  identifier;
   string asset;
   string action;
   string price;
   string tp;
   string sl;
   string lot;
};

CopySignal Signal = {};
datetime   lastPublishTime = 0;
bool       isCopyTradingBot = false;
string     eaName = "";

ulong   g_publishedIds[];
string  g_publishedAssets[];

//+------------------------------------------------------------------+
int FindPublishedIndex(const ulong pos_id) {
   int n = ArraySize(g_publishedIds);
   for(int i = 0; i < n; i++) {
      if(g_publishedIds[i] == pos_id)
         return i;
   }
   return -1;
}

//+------------------------------------------------------------------+
void MarkPositionPublished(const ulong pos_id, const string asset) {
   if(FindPublishedIndex(pos_id) >= 0)
      return;
   int n = ArraySize(g_publishedIds);
   ArrayResize(g_publishedIds, n + 1);
   ArrayResize(g_publishedAssets, n + 1);
   g_publishedIds[n] = pos_id;
   g_publishedAssets[n] = asset;
}

//+------------------------------------------------------------------+
void UnmarkPublishedAt(const int index) {
   int n = ArraySize(g_publishedIds);
   if(index < 0 || index >= n)
      return;
   for(int i = index; i < n - 1; i++) {
      g_publishedIds[i] = g_publishedIds[i + 1];
      g_publishedAssets[i] = g_publishedAssets[i + 1];
   }
   ArrayResize(g_publishedIds, n - 1);
   ArrayResize(g_publishedAssets, n - 1);
}

//+------------------------------------------------------------------+
bool IsPositionLive(const ulong pos_id) {
   for(int i = 0; i < PositionsTotal(); i++) {
      ulong ticket = PositionGetTicket(i);
      if(!PositionSelectByTicket(ticket))
         continue;
      if(PositionGetInteger(POSITION_IDENTIFIER) == pos_id)
         return true;
   }
   return false;
}

//+------------------------------------------------------------------+
void StringToUtf8Payload(const string text, uchar &out[]) {
   int n = StringToCharArray(text, out, 0, WHOLE_ARRAY, CP_UTF8);
   if(n > 0 && out[n - 1] == 0)
      ArrayResize(out, n - 1);
}

//+------------------------------------------------------------------+
bool HttpGet(const string url, string &response, int &httpCode) {
   uchar postData[];
   uchar resultData[];
   string resultHeaders;
   string requestHeaders = "Accept: application/json\r\n";
   ResetLastError();
   httpCode = WebRequest("GET", url, requestHeaders, 15000, postData, resultData, resultHeaders);
   response = CharArrayToString(resultData, 0, WHOLE_ARRAY, CP_UTF8);
   if(httpCode == -1) {
      Print("WebRequest GET failed. Error=", GetLastError(), " URL=", url);
      return false;
   }
   return true;
}

//+------------------------------------------------------------------+
bool HttpPostJson(const string url, const string jsonBody, string &response, int &httpCode) {
   uchar jsonData[];
   StringToUtf8Payload(jsonBody, jsonData);
   uchar resultData[];
   string resultHeaders;
   string requestHeaders = "Content-Type: application/json\r\nAccept: application/json\r\n";
   ResetLastError();
   httpCode = WebRequest("POST", url, requestHeaders, 15000, jsonData, resultData, resultHeaders);
   response = CharArrayToString(resultData, 0, WHOLE_ARRAY, CP_UTF8);
   if(httpCode == -1) {
      Print("WebRequest POST failed. Error=", GetLastError(), " URL=", url);
      return false;
   }
   return true;
}

//+------------------------------------------------------------------+
bool LoginEa(const string eaCode) {
   string url = ApiBase() + "/ea-auth?key=" + eaCode;
   string response = "";
   int httpCode = 0;

   if(!HttpGet(url, response, httpCode)) {
      MessageBox(
         "Allow WebRequest for https://www.nextradeai.io in Tools → Options → Expert Advisors, then re-attach EATRADE.",
         "NexTradeAI — WebRequest blocked",
         MB_ICONINFORMATION
      );
      return false;
   }

   Print("Auth HTTP ", httpCode, " — ", response);

   if(httpCode >= 200 && httpCode < 300 && StringFind(response, "\"message\":\"accept\"") >= 0) {
      isCopyTradingBot = (StringFind(response, "\"martingale\":1") >= 0 ||
                          StringFind(response, "\"martingale\": 1") >= 0 ||
                          StringFind(response, "\"ea_martingale\":true") >= 0 ||
                          StringFind(response, "\"ea_martingale\":1") >= 0);

      int namePos = StringFind(response, "\"ea_name\":\"");
      if(namePos >= 0) {
         int start = namePos + 11;
         int end = StringFind(response, "\"", start);
         if(end > start)
            eaName = StringSubstr(response, start, end - start);
      }

      Print("NexTradeAI login OK — EA: ", eaName, " | copy trading: ", isCopyTradingBot ? "yes" : "no");
      return true;
   }

   if(httpCode == 203 || StringFind(response, "invalid_ea_key") >= 0)
      MessageBox("Invalid secret code. Copy it from nextradeai.io → Admin → EAs.", "NexTradeAI", MB_ICONINFORMATION);
   else
      MessageBox("Login failed (HTTP " + IntegerToString(httpCode) + ")", "NexTradeAI", MB_ICONINFORMATION);

   return false;
}

//+------------------------------------------------------------------+
bool SendSignal(const string eaCode) {
   string lotField = "";
   if(Signal.lot != "" && Signal.lot != "0")
      lotField = ",\"lot\":\"" + Signal.lot + "\"";

   string reqString = "{\"ea_secret\":\"" + eaCode + "\",\"signal\":{\"asset\":\"" + Signal.asset +
                     "\",\"type\":\"all\",\"action\":\"" + Signal.action + "\",\"price\":\"" +
                     Signal.price + "\",\"tp\":\"" + Signal.tp + "\",\"sl\":\"" + Signal.sl + "\"" + lotField + "}}";

   string response = "";
   int httpCode = 0;
   string url = ApiBase() + "/post-signal";

   if(!HttpPostJson(url, reqString, response, httpCode)) {
      Print("Publish failed — network error");
      return false;
   }

   Print("Publish HTTP ", httpCode, " — ", response);

   if(httpCode >= 200 && httpCode < 300 && StringFind(response, "\"message\":\"accept\"") >= 0)
      return true;

   if(StringFind(response, "symbol_not_allowed") >= 0)
      Print("Symbol not on this automation's Quotes list — add it in nextradeai.io admin.");

   return false;
}

//+------------------------------------------------------------------+
bool CloseSignalOnServer(const string eaCode, const string asset) {
   string reqString = "{\"ea_secret\":\"" + eaCode + "\",\"asset\":\"" + asset + "\"}";
   string response = "";
   int httpCode = 0;
   string url = ApiBase() + "/close-signal";

   if(!HttpPostJson(url, reqString, response, httpCode))
      return false;

   Print("Close signal ", asset, " — HTTP ", httpCode, " — ", response);
   return (httpCode >= 200 && httpCode < 300 && StringFind(response, "\"message\":\"accept\"") >= 0);
}

//+------------------------------------------------------------------+
int VolumeDigits(const string symbol) {
   double volStep = SymbolInfoDouble(symbol, SYMBOL_VOLUME_STEP);
   if(volStep <= 0.0)
      return 2;
   return (int)MathMax(0, MathRound(-MathLog10(volStep)));
}

//+------------------------------------------------------------------+
void CheckClosedPositions(const string eaCode) {
   for(int i = ArraySize(g_publishedIds) - 1; i >= 0; i--) {
      ulong pos_id = g_publishedIds[i];
      if(IsPositionLive(pos_id))
         continue;

      string asset = g_publishedAssets[i];
      Print("Position closed — removing NexTrade signal for ", asset);
      CloseSignalOnServer(eaCode, asset);
      UnmarkPublishedAt(i);
   }
}

//+------------------------------------------------------------------+
void CheckPositions(const string eaCode) {
   CheckClosedPositions(eaCode);

   if(PositionsTotal() == 0)
      return;

   for(int i = 0; i < PositionsTotal(); i++) {
      ulong ticket = PositionGetTicket(i);
      if(!PositionSelectByTicket(ticket)) {
         Print("Failed to select position ticket=", ticket, " err=", GetLastError());
         continue;
      }

      ulong pos_id = PositionGetInteger(POSITION_IDENTIFIER);
      if(FindPublishedIndex(pos_id) >= 0)
         continue;

      string pos_asset = PositionGetString(POSITION_SYMBOL);
      int symDigits = (int)SymbolInfoInteger(pos_asset, SYMBOL_DIGITS);
      string pos_action = (PositionGetInteger(POSITION_TYPE) == POSITION_TYPE_BUY) ? "buy" : "sell";
      string pos_price = DoubleToString(PositionGetDouble(POSITION_PRICE_OPEN), symDigits);
      string pos_tp = DoubleToString(PositionGetDouble(POSITION_TP), symDigits);
      string pos_sl = DoubleToString(PositionGetDouble(POSITION_SL), symDigits);
      string pos_lot = DoubleToString(PositionGetDouble(POSITION_VOLUME), VolumeDigits(pos_asset));

      if(TimeCurrent() - lastPublishTime < 2)
         continue;

      Signal.identifier = pos_id;
      Signal.asset = pos_asset;
      Signal.action = pos_action;
      Signal.price = pos_price;
      Signal.tp = (PositionGetDouble(POSITION_TP) > 0.0) ? pos_tp : "0";
      Signal.sl = (PositionGetDouble(POSITION_SL) > 0.0) ? pos_sl : "0";
      Signal.lot = pos_lot;

      Print("Publishing copy signal: ", pos_action, " ", pos_asset, " lot=", pos_lot);

      if(SendSignal(eaCode)) {
         MarkPositionPublished(pos_id, pos_asset);
         lastPublishTime = TimeCurrent();
      }
   }
}

//+------------------------------------------------------------------+
int OnInit() {
   lastPublishTime = 0;
   ArrayResize(g_publishedIds, 0);
   ArrayResize(g_publishedAssets, 0);

   string eaCode = TrimText(eaCodeInput);
   if(eaCode == "") {
      MessageBox(
         "Enter your automation secret code from nextradeai.io (Admin → EAs → Secret code).",
         "NexTradeAI EATRADE",
         MB_ICONINFORMATION
      );
      return INIT_FAILED;
   }

   if(!LoginEa(eaCode))
      return INIT_FAILED;

   Signal.identifier = 0;
   Signal.asset = "";
   Signal.action = "";
   Signal.price = "";
   Signal.tp = "";
   Signal.sl = "";
   Signal.lot = "";

   Comment("NexTradeAI EATRADE — connected\nPublishing open positions to copy-trade signals…");
   Print("EATRADE v1.00 on ", Symbol(), " | API: ", ApiBase());
   return INIT_SUCCEEDED;
}

//+------------------------------------------------------------------+
void OnDeinit(const int reason) {
   Comment("");
}

//+------------------------------------------------------------------+
void OnTick() {
   string eaCode = TrimText(eaCodeInput);
   if(eaCode == "")
      return;

   string status = "NexTradeAI EATRADE";
   if(eaName != "")
      status += " — " + eaName;
   status += "\nMonitoring positions…";
   Comment(status);

   CheckPositions(eaCode);
}
